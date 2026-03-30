<x-filament-panels::page>
    @php
        $user = auth()->user();
        $sg = $record->subjectGrade;
        $canEdit = $user && $user->can('create', [App\Models\LessonPlanVersion::class, $record]);
        $canMarkOfficial = $user && $selectedVersion && $user->can('markOfficial', $selectedVersion);
        $canTranslate = $user && $user->can('translate', $record);
        $canRequestDeletion = $user && $selectedVersion
            && $user->can('requestDeletion', $selectedVersion)
            && ! $this->hasPendingDeletion;
        $canAskAi = $user && $selectedVersion && $user->can('askAi', $selectedVersion);
        $favorite = $this->userFavorite;
        $isOfficialSelected = $selectedVersion && $record->official_version_id === $selectedVersion->id;
        $differsFromOfficial = $favorite && $record->official_version_id && $favorite->lesson_plan_version_id !== $record->official_version_id;
    @endphp

    {{-- Header info --}}
    <div class="mb-4">
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
        <div class="mb-4 flex flex-wrap items-center" style="gap: 1.25rem;">
            <x-filament::button wire:click="saveNewVersion">Save New Version</x-filament::button>

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
        <div class="mb-4 max-w-md">
            <x-filament::input.wrapper label="Revision note (optional)">
                <x-filament::input wire:model="revisionNote" type="text" />
            </x-filament::input.wrapper>
        </div>

        {{-- Load marked.js for client-side preview (same CDN + SRI as Create page) --}}
        <script>
            (function () {
                if (!window.marked && !document.querySelector('script[src*="marked@17"]')) {
                    var s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/marked@17.0.5/marked.min.js';
                    s.integrity = 'sha384-tkjnnf9Tzhv5ZFrDroGvUExw9C3EVFo0RFRkzKR8ZX4b5Psoec4yb1PlD8Jh4j4H';
                    s.crossOrigin = 'anonymous';
                    document.head.appendChild(s);
                }
            })();
        </script>

        {{-- Side-by-side: Edit Window | View Window (stacked on mobile) --}}
        <div
            style="display: flex; flex-wrap: wrap; gap: 1rem;"
            x-data="{
                preview: '',
                renderMarkdown(val) {
                    this.preview = window.marked ? marked.parse(val || '') : (val || '');
                },
                init() {
                    const initial = this.$wire.get('editContent') ?? '';
                    this.renderMarkdown(initial);
                    this.$wire.watch('editContent', (val) => this.renderMarkdown(val ?? ''));
                }
            }"
            x-on:edit-input.window="renderMarkdown($event.detail.value)"
        >
            {{-- Left: Edit Window --}}
            <div style="flex: 1; min-width: 18rem;">
                <h3 class="mb-2 text-center text-sm font-semibold">Edit Window</h3>
                <textarea
                    wire:model="editContent"
                    x-on:input.debounce.300ms="$dispatch('edit-input', {value: $event.target.value})"
                    rows="28"
                    class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                ></textarea>
            </div>

            {{-- Right: View Window --}}
            <div style="flex: 1; min-width: 18rem;">
                <h3 class="mb-2 text-center text-sm font-semibold">View Window</h3>
                <div
                    x-html="preview || '<p style=\'color:#9ca3af\'>Start editing to see a preview\u2026</p>'"
                    class="prose max-w-none overflow-y-auto rounded-lg border border-gray-200 p-4 dark:border-gray-700 dark:bg-gray-800"
                    style="min-height: 40rem;"
                ></div>
            </div>
        </div>

        @if($canAskAi)
            <div class="mt-4">
                <x-filament::button wire:click="$set('aiPanelOpen', true)" color="gray" icon="heroicon-o-sparkles">
                    Ask AI
                </x-filament::button>
            </div>
        @endif

        {{-- AI panel --}}
        @include('filament.app.partials.ai-panel')

    @else
        {{-- Normal view: sidebar + content --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            {{-- Version list sidebar --}}
            <div class="lg:col-span-1">
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
                        <p class="mb-2 text-xs text-gray-500">Compare with:</p>
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
            <div class="lg:col-span-3">
                @if($selectedVersion)
                    @if($compareMode && $compareVersion)
                        {{-- Compare mode: rendered side-by-side --}}
                        <x-filament::section>
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold">
                                    v{{ $selectedVersion->version }}
                                    <span class="text-gray-400 mx-1">vs</span>
                                    v{{ $compareVersion->version }}
                                </span>
                                <x-filament::button wire:click="$set('compareMode', false)" color="gray" size="sm">
                                    Exit Compare
                                </x-filament::button>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">v{{ $selectedVersion->version }}</p>
                                    <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                        @markdown($selectedVersion->content)
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">v{{ $compareVersion->version }}</p>
                                    <div class="prose max-w-none rounded border border-gray-200 p-4 text-sm">
                                        @markdown($compareVersion->content)
                                    </div>
                                </div>
                            </div>
                        </x-filament::section>

                    @else
                        {{-- View mode --}}
                        <x-filament::section>
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
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

                                <div class="flex flex-wrap gap-2">
                                    {{-- Favorite --}}
                                    <x-filament::button wire:click="favorite" color="gray" size="sm" icon="heroicon-o-star">
                                        {{ $favorite && $favorite->lesson_plan_version_id === $selectedVersion->id ? '★ Favorited' : '☆ Favorite' }}
                                    </x-filament::button>

                                    {{-- Mark Official --}}
                                    @if($canMarkOfficial && !$isOfficialSelected)
                                        <x-filament::button wire:click="markOfficial" color="gray" size="sm">
                                            Mark Official
                                        </x-filament::button>
                                    @endif

                                    {{-- Edit --}}
                                    @if($canEdit)
                                        <x-filament::button wire:click="enterEditMode" size="sm">
                                            Edit This Plan
                                        </x-filament::button>
                                    @endif

                                    {{-- Translate --}}
                                    @if($canTranslate && $record->language === 'en' && $translationState === 'idle')
                                        <x-filament::button wire:click="startTranslation" size="sm" color="gray" icon="heroicon-o-language">
                                            Translate to Swahili
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

                            {{-- Deletion request confirmation modal --}}
                            @if($showDeletionForm)
                                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
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

                    {{-- Translation panel --}}
                    @if($canTranslate && $translationState !== 'idle')
                        <x-filament::section heading="Translate to Swahili" class="mt-4">

                            @if($translationState === 'streaming')
                                <p class="mb-3 text-sm text-gray-500">
                                    Translating — please wait. This may take up to 30 seconds.
                                </p>
                                <div class="rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap min-h-[6rem]">
                                    <span wire:stream="translatePreview">{{ $translateContent }}</span>
                                    <span class="text-gray-400 italic">▌</span>
                                </div>
                            @endif

                            @if(in_array($translationState, ['review', 'conflict']))
                                <p class="mb-3 text-sm text-gray-500">
                                    Review the translation below. You can edit it before saving.
                                    The AI does not auto-apply changes — you must click <strong>Save Translation</strong>.
                                </p>

                                <textarea
                                    wire:model="translateContent"
                                    rows="20"
                                    class="w-full rounded border border-gray-300 p-2 text-sm font-mono dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                ></textarea>

                                @if($translationState === 'conflict')
                                    <div class="mt-3 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm dark:border-yellow-700 dark:bg-yellow-950">
                                        <p class="font-semibold text-yellow-800 dark:text-yellow-200">Version conflict</p>
                                        <p class="mt-1 text-yellow-700 dark:text-yellow-300">
                                            A Swahili version {{ $selectedVersion->version }} already exists for this lesson.
                                            Choose how to number the new version:
                                        </p>
                                        <div class="mt-2 flex gap-3">
                                            @foreach(['patch' => 'Patch', 'minor' => 'Minor', 'major' => 'Major'] as $bump => $label)
                                                <label class="flex items-center gap-1 text-sm">
                                                    <input
                                                        type="radio"
                                                        wire:model="translationBump"
                                                        value="{{ $bump }}"
                                                        name="translationBump"
                                                    >
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-3 flex gap-2">
                                    <x-filament::button
                                        wire:click="saveTranslation"
                                        wire:loading.attr="disabled"
                                        wire:target="saveTranslation"
                                        color="success"
                                        size="sm"
                                        icon="heroicon-o-check"
                                    >
                                        Save Translation
                                    </x-filament::button>
                                    <x-filament::button wire:click="cancelTranslation" color="gray" size="sm">
                                        Cancel
                                    </x-filament::button>
                                </div>
                            @endif

                        </x-filament::section>
                    @endif

                    {{-- AI panel --}}
                    @include('filament.app.partials.ai-panel')
                @else
                    <p class="text-gray-500">No versions yet.</p>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
