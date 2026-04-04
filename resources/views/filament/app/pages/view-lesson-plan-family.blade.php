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
        @php
            $previews = $this->versionPreviews();
            $editContentHtml = \Illuminate\Support\Str::markdown($editContent ?? '', ['html_input' => 'strip']);
        @endphp

        {{-- Action bar: Save / version bump / Discard --}}
        <div class="mb-4 flex flex-wrap items-center" style="gap: 1.25rem;" data-noprint>
            <x-filament::button wire:click="saveNewVersion">Save Edits</x-filament::button>

            <div class="flex flex-wrap" style="gap: 1rem;">
                @foreach(['major', 'minor', 'patch'] as $bump)
                    <label wire:key="bump-{{ $bump }}" class="flex cursor-pointer items-center" style="gap: 0.375rem; font-size: 0.875rem;">
                        <input type="radio" name="versionBump" wire:model.live="versionBump" value="{{ $bump }}">
                        {{ ucfirst($bump) }} ({{ $previews[$bump] }})
                    </label>
                @endforeach
            </div>

            <x-filament::button wire:click="$set('editMode', false)" color="gray">Discard Edits</x-filament::button>
        </div>

        {{-- Revision note --}}
        <div class="mb-4 max-w-md" data-noprint>
            <x-filament::input.wrapper label="Revision note (optional)">
                <x-filament::input wire:model="revisionNote" type="text" />
            </x-filament::input.wrapper>
        </div>

        {{-- Tabbed editor with text-selection-to-source mapping --}}
        <x-filament::section>
            <div
                x-data="{
                    tab: 'preview',
                    selText: '',
                    selBefore: '',
                    selAfter: '',
                    btnVisible: false,
                    btnX: 0,
                    btnY: 0,
                    ambiguous: false,
                    captureSelection() {
                        const sel = window.getSelection();
                        if (!sel || sel.isCollapsed || !sel.toString().trim()) {
                            this.btnVisible = false;
                            return;
                        }
                        const range = sel.getRangeAt(0);
                        const container = $el.querySelector('[data-prose-target]');
                        if (!container || !container.contains(range.commonAncestorContainer)) {
                            this.btnVisible = false;
                            return;
                        }
                        this.selText = sel.toString();
                        const beforeRange = document.createRange();
                        beforeRange.setStart(container, 0);
                        beforeRange.setEnd(range.startContainer, range.startOffset);
                        const textBefore = beforeRange.toString();
                        const afterRange = document.createRange();
                        afterRange.setStart(range.endContainer, range.endOffset);
                        afterRange.setEnd(container, container.childNodes.length);
                        const textAfter = afterRange.toString();
                        this.selBefore = textBefore.slice(-120);
                        this.selAfter  = textAfter.slice(0, 120);
                        const rect = range.getBoundingClientRect();
                        const scrollY = window.scrollY || document.documentElement.scrollTop;
                        this.btnX = rect.left + rect.width / 2;
                        this.btnY = rect.top - 44;
                        this.btnVisible = true;
                        this.ambiguous = false;
                    },
                    editSelected() {
                        this.btnVisible = false;
                        this.ambiguous = false;

                        const ta = $el.querySelector('textarea[data-source-textarea]');
                        if (!ta) { this.tab = 'source'; return; }

                        const src    = ta.value;
                        const needle = this.selText.trim();
                        if (!needle) { this.tab = 'source'; return; }

                        const hits = [];
                        let i = 0;
                        while ((i = src.indexOf(needle, i)) !== -1) { hits.push(i); i += needle.length; }

                        if (hits.length === 0) { this.tab = 'source'; this.ambiguous = true; return; }

                        let start = hits[0];

                        if (hits.length > 1) {
                            const ctxB = this.selBefore.trim();
                            const ctxA = this.selAfter.trim();
                            if (!ctxB && !ctxA) { this.tab = 'source'; this.ambiguous = true; return; }
                            let best = -1, bestScore = -1, bestCount = 0;
                            for (const h of hits) {
                                let score = 0;
                                if (ctxB && src.slice(Math.max(0, h - 120), h).includes(ctxB)) score++;
                                if (ctxA && src.slice(h + needle.length, h + needle.length + 120).includes(ctxA)) score++;
                                if (score > bestScore) { bestScore = score; best = h; bestCount = 1; }
                                else if (score === bestScore) { bestCount++; }
                            }
                            if (bestCount !== 1) { this.tab = 'source'; this.ambiguous = true; return; }
                            start = best;
                        }

                        ta.setSelectionRange(start, start + needle.length);
                        const linesBefore = src.slice(0, start).split('\n').length;
                        const lh = parseInt(getComputedStyle(ta).lineHeight) || 20;
                        ta.scrollTop = Math.max(0, (linesBefore - 3) * lh);

                        this.tab = 'source';
                        requestAnimationFrame(() => ta.focus());
                    },
                }"
                @mouseup.window="captureSelection()"
                @keyup.window.debounce.150ms="if (!window.getSelection()?.toString().trim()) { btnVisible = false; }"
            >
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <x-filament::button @click="tab = 'preview'" x-show="tab === 'preview'">View Lesson</x-filament::button>
                    <x-filament::button @click="tab = 'preview'" x-show="tab !== 'preview'" color="gray">View Lesson</x-filament::button>
                    <x-filament::button @click="tab = 'source'" x-show="tab === 'source'">Edit Lesson</x-filament::button>
                    <x-filament::button @click="tab = 'source'" x-show="tab !== 'source'" color="gray">Edit Lesson</x-filament::button>
                </div>

                <div x-show="tab === 'preview'" data-prose-target>
                    @include('filament.forms.components.markdown-preview', [
                        'wireProp' => 'editContent',
                        'initialContent' => $editContent ?? '',
                        'initialHtml' => $editContentHtml,
                    ])
                </div>

                <div x-show="tab === 'source'">
                    <textarea
                        wire:model="editContent"
                        x-on:input.debounce.300ms="$dispatch('markdown-input', {value: $event.target.value})"
                        rows="28"
                        class="rounded-lg border border-gray-300 p-3 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        style="width: 100%; box-sizing: border-box;"
                        data-source-textarea
                    ></textarea>
                </div>

                {{-- Ambiguous-match banner --}}
                <div
                    x-show="ambiguous"
                    x-transition
                    class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300"
                    style="display:none;"
                >
                    The selected text appears more than once in the source — please locate it manually in the editor.
                    <button @click="ambiguous = false" style="margin-left:0.75rem;text-decoration:underline;cursor:pointer;">Dismiss</button>
                </div>

                {{-- Floating "Edit Selected Text" button --}}
                <div
                    x-show="tab === 'preview' && btnVisible"
                    x-transition.opacity
                    :style="`position:fixed; top:${btnY}px; left:${btnX}px; transform:translateX(-50%); z-index:9999;`"
                    style="display:none;"
                >
                    <button
                        @mousedown.prevent="editSelected()"
                        style="background:#1d4ed8;color:#fff;border:none;border-radius:0.375rem;padding:0.375rem 0.875rem;font-size:0.8125rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.18);white-space:nowrap;"
                    >Edit Selected Text</button>
                </div>
            </div>
        </x-filament::section>

    @else
        {{-- Normal view: sidebar + content --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            {{-- Version list sidebar --}}
            <div class="lg:col-span-1" data-noprint>
                <x-filament::section heading="Versions">
                    <ul class="space-y-1">
                        @foreach($record->versions->sortByDesc('created_at') as $v)
                            <li>
                                <button
                                    wire:click="selectVersion({{ $v->id }})"
                                    class="flex w-full items-center justify-between rounded px-2 py-1 text-sm {{ $selectedVersion && $selectedVersion->id === $v->id ? 'bg-primary-100 font-bold' : 'hover:bg-gray-100' }}"
                                >
                                    <span>v{{ $v->version }}</span>
                                    <span class="flex gap-1">
                                        @if($record->official_version_id === $v->id)
                                            <span class="rounded bg-green-100 px-1 text-xs text-green-700">Official</span>
                                        @endif
                                        @if($favorite && $favorite->lesson_plan_version_id === $v->id)
                                            <span class="text-amber-400">★</span>
                                        @endif
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>

                {{-- Compare mode selector --}}
                @if(!$compareMode && $selectedVersion && $record->versions->count() > 1)
                    <x-filament::section heading="Compare" class="mt-4">
                        {{-- Quick compare shortcuts --}}
                        <div class="mb-3 flex flex-col gap-1">
                            <x-filament::button
                                wire:click="compareToPreviousVersion"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-arrow-left"
                            >
                                Compare to Previous
                            </x-filament::button>

                            @if($record->official_version_id && $record->official_version_id !== $selectedVersion?->id)
                                <x-filament::button
                                    wire:click="compareToOfficialVersion"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-check-badge"
                                >
                                    Compare to Official
                                </x-filament::button>
                            @endif
                        </div>

                        <p class="mb-2 text-xs text-gray-500">Compare with any version:</p>
                        @foreach($record->versions->sortByDesc('created_at') as $v)
                            @if($v->id !== $selectedVersion->id)
                                <button
                                    wire:click="enterCompareMode({{ $v->id }})"
                                    class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100"
                                >
                                    v{{ $v->version }}
                                </button>
                            @endif
                        @endforeach
                    </x-filament::section>
                @endif
            </div>

            {{-- Main content area --}}
            <div class="lg:col-span-3" id="print-area">
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
                                <div class="flex gap-2">
                                    <x-filament::button
                                        wire:click="toggleDiffLayout"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-o-arrows-right-left"
                                    >
                                        {{ $diffLayout === 'side-by-side' ? 'Stacked' : 'Side-by-Side' }}
                                    </x-filament::button>
                                    <x-filament::button wire:click="$set('compareMode', false)" color="gray" size="sm">
                                        Exit Compare
                                    </x-filament::button>
                                </div>
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
                                    <div>
                                        <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                            @markdown($compareVersion->content)
                                        </div>
                                    </div>
                                    @if($diffLayout === 'side-by-side')
                                    <div>
                                        <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                            @markdown($selectedVersion->content)
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </x-filament::section>

                    @else
                        {{-- View mode --}}
                        <x-filament::section>
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-2" data-noprint>
                                <div>
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
                                </div>

                                <div class="flex flex-wrap" style="gap: 0.5rem;">
                                    {{-- Favorite --}}
                                    <x-filament::button wire:click="favorite" color="gray" size="sm" icon="heroicon-o-star">
                                        {{ $favorite && $favorite->lesson_plan_version_id === $selectedVersion->id ? '★ Favorited' : 'Mark as Favorite' }}
                                    </x-filament::button>

                                    {{-- Mark Official --}}
                                    @if($canMarkOfficial && !$isOfficialSelected)
                                        <x-filament::button wire:click="markOfficial" color="gray" size="sm">
                                            Mark as Official
                                        </x-filament::button>
                                    @endif

                                    {{-- Print --}}
                                    <x-filament::button
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-o-printer"
                                        x-on:click="window.print()"
                                    >
                                        Print
                                    </x-filament::button>

                                    {{-- Download PDF --}}
                                    @if($selectedVersion)
                                        <a
                                            href="{{ route('lesson-plan.pdf', ['family' => $record->id, 'version' => $selectedVersion->id]) }}"
                                            target="_blank"
                                        >
                                            <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-down-tray" tag="span">
                                                Download PDF
                                            </x-filament::button>
                                        </a>
                                    @endif

                                    {{-- Email PDF --}}
                                    <x-filament::button
                                        wire:click="openEmailPdfModal"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-o-envelope"
                                    >
                                        Email PDF
                                    </x-filament::button>

                                    {{-- Message About This Lesson --}}
                                    @if($canMessage)
                                        <x-filament::button
                                            wire:click="openMessageModal('author')"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-chat-bubble-left-right"
                                        >
                                            Message About This Lesson
                                        </x-filament::button>
                                    @endif

                                    {{-- Translate to Swahili --}}
                                    @if($canTranslate)
                                        <x-filament::button
                                            wire:click="openTranslationPanel"
                                            wire:loading.attr="disabled"
                                            wire:target="openTranslationPanel,translatePreview"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-language"
                                        >
                                            Translate to Swahili
                                        </x-filament::button>
                                    @endif

                                    {{-- Ask AI --}}
                                    @if($canAskAi)
                                        <x-filament::button
                                            wire:click="openAiPanel"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-o-sparkles"
                                        >
                                            Ask AI
                                        </x-filament::button>
                                    @endif

                                    {{-- Edit --}}
                                    @if($canEdit)
                                        <x-filament::button wire:click="enterEditMode" size="sm">
                                            Edit This Plan
                                        </x-filament::button>
                                    @endif

                                    {{-- Request Deletion --}}
                                    @if($canRequestDeletion)
                                        <x-filament::button
                                            wire:click="$set('showDeletionForm', true)"
                                            color="danger"
                                            size="sm"
                                            icon="heroicon-o-trash"
                                        >
                                            Request Deletion
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            {{-- Deletion request confirmation --}}
                            @if($showDeletionForm)
                                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950" data-noprint>
                                    <h3 class="mb-2 text-sm font-semibold text-red-800 dark:text-red-300">Request deletion of version {{ $selectedVersion->version }}?</h3>
                                    <p class="mb-3 text-xs text-red-700 dark:text-red-400">
                                        This submits a deletion request. A Site Admin must approve and carry out the actual deletion. The contributor and all Site Admins will be notified by inbox message.
                                    </p>
                                    <div class="mb-3">
                                        <label class="mb-1 block text-xs font-medium text-red-800 dark:text-red-300">Reason (optional)</label>
                                        <textarea
                                            wire:model="deletionReason"
                                            rows="3"
                                            class="w-full rounded border border-red-300 p-2 text-sm dark:border-red-700 dark:bg-red-900 dark:text-red-100"
                                            placeholder="Explain why this version should be deleted…"
                                        ></textarea>
                                    </div>
                                    <div class="flex gap-2">
                                        <x-filament::button wire:click="requestDeletion" color="danger" size="sm">
                                            Submit Request
                                        </x-filament::button>
                                        <x-filament::button wire:click="$set('showDeletionForm', false)" color="gray" size="sm">
                                            Cancel
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endif

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

        </div>

        {{-- Messaging modal --}}
        @include('filament.app.partials.message-modal')

        {{-- Email PDF modal --}}
        @include('filament.app.partials.email-pdf-modal')

        {{-- AI panel — full width, below the version grid --}}
        @include('filament.app.partials.ai-panel')

        {{-- Translation preview — full width, below the AI panel --}}
        @include('filament.app.partials.translation-preview-panel')
    @endif
</x-filament-panels::page>
