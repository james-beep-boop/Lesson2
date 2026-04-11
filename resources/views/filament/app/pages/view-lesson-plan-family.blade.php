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

    @once
    <style>
        .ares-compare-viewer {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
        }
        .ares-compare-panes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .ares-compare-labels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 639px) {
            .ares-compare-panes { grid-template-columns: 1fr; }
            .ares-compare-labels { display: none; }
        }
        /* Block-level diff highlights */
        .ares-diff-deleted {
            background-color: #fee2e2;
            border-left: 3px solid #f87171;
            padding-left: 0.5rem;
            margin-left: -0.5rem;
        }
        .ares-diff-added {
            background-color: #dcfce7;
            border-left: 3px solid #4ade80;
            padding-left: 0.5rem;
            margin-left: -0.5rem;
        }
        .dark .ares-diff-deleted { background-color: #450a0a; border-left-color: #dc2626; }
        .dark .ares-diff-added   { background-color: #052e16; border-left-color: #16a34a; }
    </style>
    @endonce

    {{-- Print CSS --}}
    @once
    <style>
        .ares-print-content { display: none; }
        .ares-print-compare { display: none; }
        @@media print {
            /* Hide everything except the print area */
            body > *:not(#print-area-wrapper) { display: none !important; }
            .fi-topbar, .fi-sidebar, .fi-header, nav, [data-noprint] { display: none !important; }
            #print-area { display: block !important; }
            .prose { max-width: none; }
            /* Show server-rendered fallback, hide async viewer */
            .ares-print-content { display: block !important; }
            .ares-print-compare { display: grid !important; grid-template-columns: 1fr 1fr; gap: 1rem; }
            .ares-toast-viewer { display: none !important; }
            .ares-diff-deleted, .ares-diff-added { background: transparent !important; border-left: none !important; padding-left: 0 !important; margin-left: 0 !important; }
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

        @once
        <style>
            .ares-edit-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; margin-bottom: 1rem; }
            .ares-bump-group { display: flex; flex-wrap: wrap; gap: 1rem; }
            .ares-bump-label { display: flex; cursor: pointer; align-items: center; gap: 0.375rem; font-size: 0.875rem; }
            .ares-revision-note { flex: 0 0 100%; max-width: 28rem; }
            .ares-paste-tip { flex: 0 0 100%; padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #bfdbfe; background: #eff6ff; font-size: 0.875rem; color: #1e40af; }
            .ares-editor-wrap { flex: 0 0 100%; min-width: 0; }
        </style>
        @endonce

        {{-- Action bar: Save / version bump / Discard --}}
        <div
            x-data="{
                editor: null,
                saving: false,
                initialMarkdown: '',
                baseLatestVersionId: {{ Js::from($baseLatestVersionId) }},

                async init() {
                    if (!window.ToastUIEditor) {
                        await window.loadToastUIEditor();
                    }
                    this.editor = new window.ToastUIEditor({
                        el: document.getElementById('toast-editor-mount-{{ $record->id }}'),
                        initialValue: {{ Js::from($editContent) }},
                        previewStyle: 'tab',
                        initialEditType: 'wysiwyg',
                        language: 'en',
                        height: '600px',
                        minHeight: '300px',
                        theme: window.getTheme(),
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
            class="ares-edit-bar"
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

            <div class="ares-bump-group">
                @foreach(['major', 'minor', 'patch'] as $bump)
                    <label wire:key="bump-{{ $bump }}" class="ares-bump-label">
                        <input type="radio" name="versionBump" wire:model.live="versionBump" value="{{ $bump }}">
                        {{ ucfirst($bump) }} ({{ $previews[$bump] }})
                    </label>
                @endforeach
            </div>

            <x-filament::button x-on:click="cancel()" color="gray">Discard Edits</x-filament::button>

            {{-- Revision note --}}
            <div class="ares-revision-note">
                <x-filament::input.wrapper label="Revision note (optional)">
                    <x-filament::input wire:model="revisionNote" type="text" />
                </x-filament::input.wrapper>
            </div>

            {{-- Paste tip banner --}}
            <div class="ares-paste-tip">
                Pasting from Word or Google Docs? Use <strong>Paste as Plain Text</strong> (Ctrl+Shift+V / Cmd+Shift+V) to avoid formatting problems.
            </div>

            {{-- Toast UI editor mount point --}}
            <div class="ares-editor-wrap">
                <x-filament::section>
                    <div wire:ignore id="toast-editor-mount-{{ $record->id }}"></div>
                </x-filament::section>
            </div>
        </div>

    @else
        @if($selectedVersion)
        {{-- ── Action button panel ─────────────────────────────────────────── --}}
        <div
            x-data="{
                openPanel: null,
                compareVersionId: {{ $record->versions->where('id', '!=', $selectedVersion->id)->sortByDesc('created_at')->first()?->id ?? 'null' }}
            }"
            class="mb-4"
            data-noprint
        >
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex flex-wrap gap-3">
                    @if($canEdit)
                        <x-filament::button wire:click="enterEditMode">
                            Edit This Plan
                        </x-filament::button>
                    @endif

                    @if($compareMode)
                        <x-filament::button
                            wire:click="$set('compareMode', false)"
                            color="gray"
                            icon="heroicon-o-x-mark"
                        >
                            Exit Compare
                        </x-filament::button>
                    @elseif($record->versions->count() > 1)
                        <x-filament::button
                            @click="openPanel = openPanel === 'compare' ? null : 'compare'"
                            color="gray"
                            icon="heroicon-o-arrows-right-left"
                        >
                            Compare Two Plans
                        </x-filament::button>
                    @endif

                    @if($canAskAi)
                        <x-filament::button
                            wire:click="openAiPanel"
                            color="gray"
                            icon="heroicon-o-sparkles"
                        >
                            Ask AI
                        </x-filament::button>
                    @endif

                    @if($canTranslate)
                        <x-filament::button
                            wire:click="openTranslationPanel"
                            wire:loading.attr="disabled"
                            wire:target="openTranslationPanel,translatePreview"
                            color="gray"
                            icon="heroicon-o-language"
                        >
                            Translate to Swahili
                        </x-filament::button>
                    @endif

                    <x-filament::button
                        @click="openPanel = openPanel === 'save' ? null : 'save'"
                        color="gray"
                        icon="heroicon-o-share"
                    >
                        Save / Send Options
                    </x-filament::button>

                    @if($canRequestDeletion)
                        <x-filament::button
                            wire:click="$set('showDeletionForm', true)"
                            color="danger"
                            icon="heroicon-o-trash"
                        >
                            Request Deletion
                        </x-filament::button>
                    @endif
                </div>
            </div>

            {{-- Compare picker — slides open below the button panel --}}
            <div
                x-show="openPanel === 'compare'"
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
                        @click="$wire.enterCompareMode(compareVersionId); openPanel = null"
                        size="sm"
                        icon="heroicon-o-arrows-right-left"
                    >
                        Compare
                    </x-filament::button>
                    <x-filament::button
                        @click="openPanel = null"
                        color="gray"
                        size="sm"
                    >
                        Cancel
                    </x-filament::button>
                </div>
            </div>

            {{-- Save / Send panel — slides open below the button panel --}}
            <div
                x-show="openPanel === 'save'"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"
                style="display: none;"
            >
                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Save, print, or share this lesson plan:</p>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button
                        color="gray"
                        icon="heroicon-o-printer"
                        size="sm"
                        x-on:click="window.print()"
                    >
                        Print
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        href="{{ route('lesson-plan.pdf', ['family' => $record->id, 'version' => $selectedVersion->id]) }}"
                        target="_blank"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        size="sm"
                    >
                        Save PDF
                    </x-filament::button>

                    <x-filament::button
                        wire:click="openEmailPdfModal"
                        @click="openPanel = null"
                        color="gray"
                        icon="heroicon-o-envelope"
                        size="sm"
                    >
                        Email PDF
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        href="{{ route('lesson-plan.docx', ['family' => $record->id, 'version' => $selectedVersion->id]) }}"
                        target="_blank"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        size="sm"
                    >
                        Save .docx
                    </x-filament::button>

                    <x-filament::button
                        wire:click="openEmailDocxModal"
                        @click="openPanel = null"
                        color="gray"
                        icon="heroicon-o-envelope"
                        size="sm"
                    >
                        Email .docx
                    </x-filament::button>

                    @if($canMessage)
                        <x-filament::button
                            wire:click="openMessageModal('author')"
                            @click="openPanel = null"
                            color="gray"
                            icon="heroicon-o-chat-bubble-left-right"
                            size="sm"
                        >
                            Message About This
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Action panels — below buttons, above lesson --}}
        @include('filament.app.partials.email-pdf-modal')
        @include('filament.app.partials.email-docx-modal')
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
                        @php
                            // Always show lower version on the left as "from", higher on the right as "to"
                            [$leftVersion, $rightVersion] = version_compare($compareVersion->version, $selectedVersion->version) <= 0
                                ? [$compareVersion, $selectedVersion]
                                : [$selectedVersion, $compareVersion];
                        @endphp
                        {{-- Compare mode --}}
                        <x-filament::section>
                            {{-- Header: version info + mode toggle + (in source mode) layout toggle --}}
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2" data-noprint>
                                <div>
                                    <span class="font-semibold">
                                        v{{ $leftVersion->version }}
                                        <span class="text-gray-400 mx-1">→</span>
                                        v{{ $rightVersion->version }}
                                    </span>
                                    <span class="ml-3 text-xs text-gray-500">
                                        ({{ $leftVersion->contributor->username ?? '?' }} → {{ $rightVersion->contributor->username ?? '?' }})
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <x-filament::button
                                        wire:click="toggleCompareView"
                                        color="gray"
                                        size="sm"
                                        :icon="$compareView === 'rendered' ? 'heroicon-o-code-bracket' : 'heroicon-o-eye'"
                                    >
                                        {{ $compareView === 'rendered' ? 'Source Diff' : 'Rendered View' }}
                                    </x-filament::button>
                                    @if($compareView === 'source')
                                        <x-filament::button
                                            wire:click="toggleDiffLayout"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-arrows-right-left"
                                        >
                                            {{ $diffLayout === 'side-by-side' ? 'Stacked' : 'Side-by-Side' }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            @if($compareView === 'rendered')
                                <div
                                    wire:key="compare-rendered-{{ $leftVersion->id }}-{{ $rightVersion->id }}"
                                    x-data="toastCompareViewers({{ Js::from($leftVersion->content) }}, {{ Js::from($rightVersion->content) }})"
                                >
                                    {{-- Version labels --}}
                                    <div class="ares-compare-labels" data-noprint>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            v{{ $leftVersion->version }} — from
                                        </div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            v{{ $rightVersion->version }} — to
                                        </div>
                                    </div>

                                    {{-- Highlight toggle --}}
                                    <div class="mb-2 flex justify-end" data-noprint>
                                        <x-filament::button
                                            x-on:click="toggleHighlights()"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-eye"
                                        >
                                            <span x-show="!highlightsEnabled">Highlight changes</span><span x-show="highlightsEnabled" style="display:none">Hide highlights</span>
                                        </x-filament::button>
                                    </div>

                                    {{-- Panes --}}
                                    <div class="ares-compare-panes">
                                        {{-- wire:ignore on the pane containers protects the entire Toast UI subtree --}}
                                        <div
                                            data-compare-pane-left
                                            wire:ignore
                                            class="ares-toast-viewer rounded border border-gray-200"
                                            style="overflow-y:auto; max-height:70vh"
                                        >
                                            <div data-toast-viewer-left></div>
                                        </div>
                                        <div
                                            data-compare-pane-right
                                            wire:ignore
                                            class="ares-toast-viewer rounded border border-gray-200"
                                            style="overflow-y:auto; max-height:70vh"
                                        >
                                            <div data-toast-viewer-right></div>
                                        </div>
                                    </div>

                                    {{-- Screen: two-column grid until viewers mount (:style > CSS); print !important overrides :style --}}
                                    <div class="ares-print-compare"
                                         :style="mounted ? 'display:none' : 'display:grid;grid-template-columns:1fr 1fr;gap:1rem'">
                                        <div class="prose max-w-none">
                                            @markdown($leftVersion->content)
                                        </div>
                                        <div class="prose max-w-none">
                                            @markdown($rightVersion->content)
                                        </div>
                                    </div>
                                </div>

                            @else
                                {{-- Source diff (existing raw-diff output) --}}
                                @if($diffLayout === 'side-by-side')
                                    <div class="mb-2 grid grid-cols-2 gap-4">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            v{{ $leftVersion->version }} — from
                                        </div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            v{{ $rightVersion->version }} — to
                                        </div>
                                    </div>
                                @else
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        v{{ $leftVersion->version }} → v{{ $rightVersion->version }}
                                    </div>
                                @endif

                                @if($diffHtml)
                                    <div class="diff-wrapper overflow-x-auto rounded border border-gray-200 text-sm">
                                        {!! $diffHtml !!}
                                    </div>
                                @endif
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

                            {{-- Content viewer — Toast UI Viewer (screen) + server-rendered fallback (print/JS-fail) --}}
                            <div
                                wire:key="toast-viewer-{{ $selectedVersion->id }}"
                                x-data="toastViewer({{ Js::from($selectedVersion->content) }})"
                            >
                                <div class="ares-toast-viewer">
                                    <div data-toast-viewer wire:ignore></div>
                                </div>
                                {{-- Screen: visible until viewer mounts (:style > CSS); print !important overrides :style --}}
                                <div class="ares-print-content prose max-w-none"
                                     :style="mounted ? 'display:none' : 'display:block'">
                                    @markdown($selectedVersion->content)
                                </div>
                            </div>
                        </x-filament::section>
                    @endif

                @else
                    <p class="text-gray-500">No versions yet.</p>
                @endif
            </div>

    @endif
</x-filament-panels::page>
