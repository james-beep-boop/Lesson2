<x-filament-panels::page>
    @php
        $user = auth()->user();
        $sg = $record->subjectGrade;
        $canEdit = $user && $user->can('create', [App\Models\LessonPlanVersion::class, $record]);
        $canTranslate = $user && $selectedVersion && $user->can('translate', $selectedVersion);
        $canMarkOfficial = $user && $selectedVersion && $user->can('markOfficial', $selectedVersion);
        $canRequestDeletion = $user && $selectedVersion
            && $user->can('requestDeletion', $selectedVersion)
            && ! $this->hasPendingDeletion;
        $canAskAi = $user && $selectedVersion && $user->can('askAi', $selectedVersion);
        $canMessage = $user && ! $user->is_system;
        $favorite = $this->userFavorite;
        $isOfficialSelected = $selectedVersion && $record->official_version_id === $selectedVersion->id;
        $differsFromOfficial = $favorite && $record->official_version_id && $favorite->lesson_plan_version_id !== $record->official_version_id;
    @endphp

    {{-- Diff CSS injected once --}}
    @if($diffCss)
        @once
        <style id="diff-css">{!! $diffCss !!}</style>
        @endonce
    @endif

    {{-- Button grid layout --}}
    @once
    <style>
        .lesson-btn-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        @@media (min-width: 640px) {
            .lesson-btn-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @@media (min-width: 1024px) {
            .lesson-btn-grid { grid-template-columns: repeat(5, 1fr); }
        }
        .lesson-btn-grid > * {
            display: flex !important;
            width: 100% !important;
            justify-content: center !important;
        }
    </style>
    @endonce

    {{-- Print CSS --}}
    @once
    <style>
        @@media print {
            /* Hide everything except the print area */
            body > *:not(#print-area-wrapper) { display: none !important; }
            .fi-topbar, .fi-sidebar, .fi-header, nav, [data-noprint] { display: none !important; }
            #print-area { display: block !important; }
            .prose { max-width: none; }
        }
        @@page { margin: 2cm; }
    </style>
    @endonce

    {{-- Header info --}}
    <div class="mb-4" data-noprint>
        <h1 class="text-xl font-bold">
            {{ $sg->subject->name }} — Grade {{ $sg->grade }} · Day {{ $record->day }}
        </h1>

        @if($differsFromOfficial)
            <p class="mt-1 text-sm text-amber-600">
                ★ Your favorited version ({{ $favorite->version->version ?? '?' }}) differs from the official version.
            </p>
        @endif
    </div>

    @if($editMode)
        @php $previews = $this->versionPreviews(); @endphp

        {{-- Action bar: Save / version bump / Discard --}}
        <div
            x-data="{
                editor: null,
                saving: false,
                initialMarkdown: '',
                baseLatestVersionId: {{ Js::from($baseLatestVersionId) }},

                async init() {
                    if (!window.ToastUIEditor) {
                        const m = await import({{ Js::from(\Illuminate\Support\Facades\Vite::asset('resources/js/toast-editor.js')) }});
                        window.ToastUIEditor = m.default;
                    }
                    this.editor = new window.ToastUIEditor({
                        el: document.getElementById('toast-editor-mount-{{ $record->id }}'),
                        initialValue: {{ Js::from($editContent) }},
                        previewStyle: 'tab',
                        initialEditType: 'wysiwyg',
                        language: 'en',
                        height: '600px',
                        minHeight: '300px',
                        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                        toolbarItems: [
                            ['heading', 'bold', 'italic', 'strike'],
                            ['ul', 'ol', 'task'],
                            ['table', 'link'],
                        ],
                    });
                    this.initialMarkdown = this.editor.getMarkdown();

                    this._beforeUnload = (e) => {
                        if (this.editor && this.editor.getMarkdown() !== this.initialMarkdown) {
                            e.preventDefault();
                            e.returnValue = '';
                        }
                    };
                    window.addEventListener('beforeunload', this._beforeUnload);
                },

                async save() {
                    if (this.saving) return;
                    this.saving = true;
                    try {
                        const md = this.editor.getMarkdown();
                        await $wire.set('editContent', md);
                        await $wire.call('saveNewVersion');
                        this.initialMarkdown = md;
                    } finally {
                        this.saving = false;
                    }
                },

                cancel() {
                    $wire.call('cancelEditMode');
                },

                destroy() {
                    window.removeEventListener('beforeunload', this._beforeUnload);
                    if (this.editor) {
                        this.editor.destroy();
                        this.editor = null;
                    }
                },
            }"
            class="mb-4 flex flex-wrap items-center"
            style="gap: 1.25rem;"
            data-noprint
        >
            <x-filament::button
                x-on:click="save()"
                x-bind:disabled="saving"
                x-bind:class="saving ? 'opacity-50 cursor-not-allowed' : ''"
            >
                <span x-show="!saving">Save Edits</span>
                <span x-show="saving" style="display:none;">Saving…</span>
            </x-filament::button>

            <div class="flex flex-wrap" style="gap: 1rem;">
                @foreach(['major', 'minor', 'patch'] as $bump)
                    <label wire:key="bump-{{ $bump }}" class="flex cursor-pointer items-center" style="gap: 0.375rem; font-size: 0.875rem;">
                        <input type="radio" name="versionBump" wire:model.live="versionBump" value="{{ $bump }}">
                        {{ ucfirst($bump) }} ({{ $previews[$bump] }})
                    </label>
                @endforeach
            </div>

            <x-filament::button x-on:click="cancel()" color="gray">Discard Edits</x-filament::button>

            {{-- Revision note --}}
            <div class="w-full max-w-md">
                <x-filament::input.wrapper label="Revision note (optional)">
                    <x-filament::input wire:model="revisionNote" type="text" />
                </x-filament::input.wrapper>
            </div>

            {{-- Paste tip banner --}}
            <div class="w-full rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-800 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-300">
                Pasting from Word or Google Docs? Use <strong>Paste as Plain Text</strong> (Ctrl+Shift+V / Cmd+Shift+V) to avoid formatting problems.
            </div>

            {{-- Toast UI editor mount point --}}
            <div class="w-full">
                <x-filament::section>
                    <div wire:ignore id="toast-editor-mount-{{ $record->id }}"></div>
                </x-filament::section>
            </div>
        </div>

    @else
        @if($selectedVersion)
        {{-- ── Action button panel ──────────────────────────────────────────── --}}
        <div
            x-data="{ compareOpen: false, compareVersionId: {{ $record->versions->where('id', '!=', $selectedVersion->id)->sortByDesc('created_at')->first()?->id ?? 'null' }} }"
            class="mb-4"
            data-noprint
        >
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="lesson-btn-grid">
                    {{-- 1: Edit This Plan --}}
                    @if($canEdit)
                        <x-filament::button wire:click="enterEditMode" class="w-full justify-center">
                            Edit This Plan
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif

                    {{-- 2: Translate to Swahili --}}
                    @if($canTranslate)
                        <x-filament::button
                            wire:click="openTranslationPanel"
                            wire:loading.attr="disabled"
                            wire:target="openTranslationPanel,translatePreview"
                            color="gray"
                            icon="heroicon-o-language"
                            class="w-full justify-center"
                        >
                            Translate to Swahili
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif

                    {{-- 3: Ask AI --}}
                    @if($canAskAi)
                        <x-filament::button
                            wire:click="openAiPanel"
                            color="gray"
                            icon="heroicon-o-sparkles"
                            class="w-full justify-center"
                        >
                            Ask AI
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif

                    {{-- 4: Compare to Other / Exit Compare --}}
                    @if($compareMode)
                        <x-filament::button
                            wire:click="$set('compareMode', false)"
                            color="gray"
                            icon="heroicon-o-x-mark"
                            class="w-full justify-center"
                        >
                            Exit Compare
                        </x-filament::button>
                    @elseif($record->versions->count() > 1)
                        <x-filament::button
                            @click="compareOpen = !compareOpen"
                            color="gray"
                            icon="heroicon-o-arrows-right-left"
                            class="w-full justify-center"
                        >
                            Compare to Other
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif

                    {{-- 5: Request Deletion --}}
                    @if($canRequestDeletion)
                        <x-filament::button
                            wire:click="$set('showDeletionForm', true)"
                            color="danger"
                            icon="heroicon-o-trash"
                            class="w-full justify-center"
                        >
                            Request Deletion
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif

                    {{-- 6: Favorite --}}
                    <x-filament::button
                        wire:click="favorite"
                        color="gray"
                        icon="heroicon-o-star"
                        class="w-full justify-center"
                    >
                        {{ $favorite && $favorite->lesson_plan_version_id === $selectedVersion->id ? '★ Favorited' : 'Favorite' }}
                    </x-filament::button>

                    {{-- 7: Print --}}
                    <x-filament::button
                        color="gray"
                        icon="heroicon-o-printer"
                        x-on:click="window.print()"
                        class="w-full justify-center"
                    >
                        Print
                    </x-filament::button>

                    {{-- 8: Save PDF --}}
                    <x-filament::button
                        tag="a"
                        href="{{ route('lesson-plan.pdf', ['family' => $record->id, 'version' => $selectedVersion->id]) }}"
                        target="_blank"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        class="w-full justify-center"
                    >
                        Save PDF
                    </x-filament::button>

                    {{-- 9: Email PDF --}}
                    <x-filament::button
                        wire:click="openEmailPdfModal"
                        color="gray"
                        icon="heroicon-o-envelope"
                        class="w-full justify-center"
                    >
                        Email PDF
                    </x-filament::button>

                    {{-- 10: Message About This --}}
                    @if($canMessage)
                        <x-filament::button
                            wire:click="openMessageModal('author')"
                            color="gray"
                            icon="heroicon-o-chat-bubble-left-right"
                            class="w-full justify-center"
                        >
                            Message About This
                        </x-filament::button>
                    @else
                        <div></div>
                    @endif
                </div>
            </div>

            {{-- Compare picker — slides open below the button panel --}}
            <div
                x-show="compareOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"
                style="display: none;"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Compare version <strong>{{ $selectedVersion->version }}</strong> with version
                    </span>
                    <select
                        x-model="compareVersionId"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        @foreach($record->versions->sortByDesc('created_at') as $v)
                            @if($v->id !== $selectedVersion->id)
                                <option value="{{ $v->id }}">
                                    v{{ $v->version }}
                                    @if($record->official_version_id === $v->id) (Official) @endif
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <x-filament::button
                        @click="$wire.enterCompareMode(compareVersionId); compareOpen = false"
                        size="sm"
                        icon="heroicon-o-arrows-right-left"
                    >
                        Compare
                    </x-filament::button>
                    <x-filament::button
                        @click="compareOpen = false"
                        color="gray"
                        size="sm"
                    >
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        </div>
        @endif

        {{-- Action panels — below buttons, above lesson --}}
        @include('filament.app.partials.email-pdf-modal')
        @include('filament.app.partials.ai-panel')
        @include('filament.app.partials.translation-preview-panel')
        @include('filament.app.partials.message-modal')

        {{-- Request Deletion panel --}}
        @if($showDeletionForm && $selectedVersion)
        <div class="mt-4" data-noprint>
            @if($isOfficialSelected)
                <x-filament::section heading="Cannot Delete Official Version">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        The official version of a lesson plan cannot be deleted. To delete this version, first mark a different version as official.
                    </p>
                    <x-filament::button wire:click="$set('showDeletionForm', false)" color="gray">
                        Close
                    </x-filament::button>
                </x-filament::section>
            @else
                <x-filament::section heading="Request Deletion of Version {{ $selectedVersion->version }}">
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        This submits a deletion request. A Site Admin must approve and carry out the actual deletion. The contributor and all Site Admins will be notified by inbox message.
                    </p>
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason (optional)</label>
                        <textarea
                            wire:model="deletionReason"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 p-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            placeholder="Explain why this version should be deleted…"
                        ></textarea>
                    </div>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="requestDeletion" color="danger">
                            Submit Request
                        </x-filament::button>
                        <x-filament::button wire:click="$set('showDeletionForm', false)" color="gray">
                            Cancel
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
        @endif

        {{-- Versions panel --}}
        @php
            $officialVersion = $record->official_version_id
                ? $record->versions->firstWhere('id', $record->official_version_id)
                : null;
            $otherVersions = $selectedVersion
                ? $record->versions->sortByDesc('created_at')->where('id', '!=', $selectedVersion->id)->values()
                : collect();
        @endphp
        <div class="mb-4" data-noprint>
            <x-filament::section>
                <div class="flex flex-wrap items-center" style="gap: 0.75rem;">
                    {{-- Official version — first --}}
                    @if($officialVersion)
                        <x-filament::button
                            color="info"
                            size="sm"
                            wire:click="selectVersion({{ $officialVersion->id }})"
                        >
                            Official version: v{{ $officialVersion->version }}
                        </x-filament::button>
                    @else
                        <x-filament::button color="info" size="sm" disabled>
                            Official version: none
                        </x-filament::button>
                    @endif

                    {{-- Now Viewing — second --}}
                    <x-filament::button color="info" size="sm" disabled>
                        Now Viewing v{{ $selectedVersion->version }}
                    </x-filament::button>

                    {{-- Other versions --}}
                    @if($otherVersions->isNotEmpty())
                        <x-filament::button color="info" size="sm" disabled>
                            Other Versions:
                        </x-filament::button>
                        @foreach($otherVersions as $v)
                            <x-filament::button
                                wire:click="selectVersion({{ $v->id }})"
                                color="gray"
                                size="sm"
                            >
                                v{{ $v->version }}{{ $record->official_version_id === $v->id ? ' (Official)' : '' }}{{ $favorite && $favorite->lesson_plan_version_id === $v->id ? ' ★' : '' }}
                            </x-filament::button>
                        @endforeach
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Main content area --}}
        <div id="print-area">
                @if($selectedVersion)
                    @if($compareMode && $compareVersion)
                        {{-- Compare mode: visual diff --}}
                        <x-filament::section>
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2" data-noprint>
                                <div>
                                    <span class="font-semibold">
                                        v{{ $compareVersion->version }}
                                        <span class="text-gray-400 mx-1">→</span>
                                        v{{ $selectedVersion->version }}
                                    </span>
                                    <span class="ml-3 text-xs text-gray-500">
                                        ({{ $compareVersion->contributor->username ?? '?' }} → {{ $selectedVersion->contributor->username ?? '?' }})
                                    </span>
                                </div>
                                <x-filament::button
                                    wire:click="toggleDiffLayout"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-arrows-right-left"
                                >
                                    {{ $diffLayout === 'side-by-side' ? 'Stacked' : 'Side-by-Side' }}
                                </x-filament::button>
                            </div>

                            {{-- Version labels --}}
                            @if($diffLayout === 'side-by-side')
                                <div class="mb-2 grid grid-cols-2 gap-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        v{{ $compareVersion->version }} — from
                                    </div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        v{{ $selectedVersion->version }} — to
                                    </div>
                                </div>
                            @else
                                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    v{{ $compareVersion->version }} → v{{ $selectedVersion->version }}
                                </div>
                            @endif

                            {{-- Diff output --}}
                            @if($diffHtml)
                                <div class="diff-wrapper overflow-x-auto rounded border border-gray-200 text-sm">
                                    {!! $diffHtml !!}
                                </div>
                            @else
                                <div class="grid grid-cols-{{ $diffLayout === 'side-by-side' ? '2' : '1' }} gap-4">
                                    <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                        @markdown($compareVersion->content)
                                    </div>
                                    @if($diffLayout === 'side-by-side')
                                    <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                        @markdown($selectedVersion->content)
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </x-filament::section>

                    @else
                        {{-- View mode --}}
                        <x-filament::section>
                            <div class="mb-4" data-noprint>
                                <p class="text-sm text-gray-500">
                                    v{{ $selectedVersion->version }} ·
                                    by {{ $selectedVersion->contributor->username ?? '?' }} ·
                                    {{ $selectedVersion->created_at->diffForHumans() }}
                                    @if($selectedVersion->revision_note)
                                        · <em>{{ $selectedVersion->revision_note }}</em>
                                    @endif
                                </p>
                                @if($isOfficialSelected)
                                    <span class="text-xs font-semibold text-green-600">✓ Official version</span>
                                @endif
                                @if($canMarkOfficial && !$isOfficialSelected)
                                    <x-filament::button wire:click="markOfficial" color="gray" size="sm" class="mt-2">
                                        Mark as Official
                                    </x-filament::button>
                                @endif
                            </div>

                            {{-- Content viewer --}}
                            <div class="prose max-w-none">
                                @markdown($selectedVersion->content)
                            </div>
                        </x-filament::section>
                    @endif

                @else
                    <p class="text-gray-500">No versions yet.</p>
                @endif
            </div>

    @endif
</x-filament-panels::page>
