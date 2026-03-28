<x-filament-panels::page>
    @php
        $user = auth()->user();
        $sg = $record->subjectGrade;
        $canEdit = $user && $user->canEditSubjectGrade($sg);
        $canMarkOfficial = $user && ($user->isSiteAdmin() || $user->isSubjectAdminFor($sg));
        $canTranslate = $user && config('features.ai_suggestions') && ($user->isSiteAdmin() || $user->isSubjectAdminFor($sg));
        $canRequestDeletion = $user && $selectedVersion
            && ($user->isSubjectAdminFor($sg) || $user->isSiteAdmin())
            && ! $this->hasPendingDeletion;
        $canAskAi = $user && config('features.ai_suggestions') && $canEdit;
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
        {{-- Edit mode: full-width --}}
        @php $previews = $this->versionPreviews(); @endphp
        <x-filament::section heading="Edit This Plan — new version">
            <div x-data="{ tab: 'edit' }">
                <div class="mb-3 flex gap-1 border-b border-gray-200">
                    <button
                        @click="tab = 'edit'"
                        :class="tab === 'edit' ? 'border-b-2 border-primary-600 font-semibold text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 text-sm -mb-px"
                    >Edit</button>
                    <button
                        @click="tab = 'preview'; $wire.$refresh()"
                        :class="tab === 'preview' ? 'border-b-2 border-primary-600 font-semibold text-primary-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-1.5 text-sm -mb-px"
                    >Preview</button>
                </div>
                <div x-show="tab === 'edit'">
                    <textarea
                        wire:model="editContent"
                        rows="28"
                        class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm"
                    ></textarea>
                </div>
                <div x-show="tab === 'preview'" class="prose max-w-none min-h-[7rem] rounded-lg border border-gray-200 p-4">
                    @markdown($editContent)
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-6">
                <div>
                    <label class="text-sm font-medium">Version bump</label>
                    <div class="mt-1 flex gap-4">
                        @foreach(['major', 'minor', 'patch'] as $bump)
                            <label wire:key="bump-{{ $bump }}" class="flex items-center gap-1 text-sm cursor-pointer">
                                <input type="radio" name="versionBump" wire:model.live="versionBump" value="{{ $bump }}">
                                {{ ucfirst($bump) }} ({{ $previews[$bump] }})
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex-1 min-w-48">
                    <x-filament::input.wrapper label="Revision note (optional)">
                        <x-filament::input wire:model="revisionNote" type="text" />
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if($canAskAi)
                <div class="mt-4">
                    <x-filament::button wire:click="$set('aiPanelOpen', true)" color="gray" icon="heroicon-o-sparkles">
                        Ask AI
                    </x-filament::button>
                </div>
            @endif

            <div class="mt-4 flex gap-3">
                <x-filament::button wire:click="saveNewVersion">Save New Version</x-filament::button>
                <x-filament::button wire:click="$set('editMode', false)" color="gray">Discard Edits</x-filament::button>
            </div>
        </x-filament::section>

        {{-- AI panel --}}
        @if($aiPanelOpen && $canAskAi)
            <x-filament::section heading="Ask AI" class="mt-4">
                <p class="mb-2 text-sm text-gray-500">AI suggestions are read-only. Copy anything useful into your editor manually.</p>
                <div class="flex gap-2 mb-3">
                    @foreach(['Suggest improvements', 'Check for clarity', 'Simplify language', 'Ask a question'] as $quick)
                        <x-filament::button wire:click="$set('aiPrompt', '{{ $quick }}')" color="gray" size="sm">
                            {{ $quick }}
                        </x-filament::button>
                    @endforeach
                </div>
                <textarea wire:model="aiPrompt" rows="3" class="w-full rounded border border-gray-300 p-2 text-sm" placeholder="What would you like help with?"></textarea>
                <div class="mt-3 rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap min-h-[2rem]">
                    <span wire:stream="aiResponse">{{ $aiResponse }}</span>
                    <span wire:loading wire:target="submitAiPrompt" class="text-gray-400 italic">Thinking…</span>
                </div>
                <div class="mt-3 flex gap-2">
                    <x-filament::button wire:click="submitAiPrompt" wire:loading.attr="disabled" wire:target="submitAiPrompt" size="sm">Submit</x-filament::button>
                    <x-filament::button wire:click="$set('aiPanelOpen', false)" color="gray" size="sm">Close</x-filament::button>
                </div>
            </x-filament::section>
        @endif

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

                    {{-- AI panel (slide-over style) --}}
                    @if($aiPanelOpen && $canAskAi)
                        <x-filament::section heading="Ask AI" class="mt-4">
                            <p class="mb-2 text-sm text-gray-500">AI suggestions are read-only. Copy anything useful into your editor manually.</p>
                            <div class="flex gap-2 mb-3">
                                @foreach(['Suggest improvements', 'Check for clarity', 'Simplify language', 'Ask a question'] as $quick)
                                    <x-filament::button wire:click="$set('aiPrompt', '{{ $quick }}')" color="gray" size="sm">
                                        {{ $quick }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                            <textarea wire:model="aiPrompt" rows="3" class="w-full rounded border border-gray-300 p-2 text-sm" placeholder="What would you like help with?"></textarea>
                            <div class="mt-3 rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap min-h-[2rem]">
                                <span wire:stream="aiResponse">{{ $aiResponse }}</span>
                                <span wire:loading wire:target="submitAiPrompt" class="text-gray-400 italic">Thinking…</span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <x-filament::button wire:click="submitAiPrompt" wire:loading.attr="disabled" wire:target="submitAiPrompt" size="sm">Submit</x-filament::button>
                                <x-filament::button wire:click="$set('aiPanelOpen', false)" color="gray" size="sm">Close</x-filament::button>
                            </div>
                        </x-filament::section>
                    @endif
                @else
                    <p class="text-gray-500">No versions yet.</p>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
