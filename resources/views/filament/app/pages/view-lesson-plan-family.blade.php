<x-filament-panels::page>
    @php
        $user = auth()->user();
        $sg = $record->subjectGrade;
        $canEdit = $user && $user->canEditSubjectGrade($sg);
        $canMarkOfficial = $user && ($user->isSiteAdmin() || $user->isSubjectAdminFor($sg));
        $canTranslate = $user && config('features.ai_suggestions') && ($user->isSiteAdmin() || $user->isSubjectAdminFor($sg));
        $canAskAi = $user && config('features.ai_suggestions') && $canEdit;
        $favorite = $user ? \App\Models\Favorite::where('user_id', $user->id)->where('lesson_plan_family_id', $record->id)->first() : null;
        $isOfficialSelected = $selectedVersion && $record->official_version_id === $selectedVersion->id;
        $differsFromOfficial = $favorite && $record->official_version_id && $favorite->lesson_plan_version_id !== $record->official_version_id;
    @endphp

    {{-- Header info --}}
    <div class="mb-4">
        <h1 class="text-xl font-bold">
            {{ $sg->subject->name }} — Grade {{ $sg->grade }} · Day {{ $record->day }} ·
            {{ $record->language === 'en' ? 'English' : 'Swahili' }}
        </h1>

        @if($differsFromOfficial)
            <p class="mt-1 text-sm text-amber-600">
                ★ Your favorited version ({{ $favorite->version->version ?? '?' }}) differs from the official version.
            </p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Version list sidebar --}}
        <div class="lg:col-span-1">
            <x-filament::section heading="Versions">
                <ul class="space-y-1">
                    @foreach($record->versions()->orderByDesc('created_at')->get() as $v)
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
            @if(!$compareMode && $selectedVersion && $record->versions()->count() > 1)
                <x-filament::section heading="Compare" class="mt-4">
                    <p class="mb-2 text-xs text-gray-500">Compare with:</p>
                    @foreach($record->versions()->orderByDesc('created_at')->get() as $v)
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
                    {{-- Compare mode: two columns, read-only --}}
                    <x-filament::section>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="font-semibold">Compare (read-only)</span>
                            <x-filament::button wire:click="$set('compareMode', false)" color="gray" size="sm">
                                Exit Compare
                            </x-filament::button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="mb-1 text-xs font-bold text-gray-500">v{{ $selectedVersion->version }}</p>
                                <pre class="overflow-auto rounded border bg-gray-50 p-3 text-sm">{{ $selectedVersion->content }}</pre>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-bold text-gray-500">v{{ $compareVersion->version }}</p>
                                <pre class="overflow-auto rounded border bg-gray-50 p-3 text-sm">{{ $compareVersion->content }}</pre>
                            </div>
                        </div>
                    </x-filament::section>

                @elseif($editMode)
                    {{-- Edit mode --}}
                    <x-filament::section heading="Edit — new version">
                        <textarea
                            wire:model="editContent"
                            rows="24"
                            class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm"
                        ></textarea>

                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            <div>
                                <label class="text-sm font-medium">Version bump</label>
                                <div class="mt-1 flex gap-2">
                                    @foreach(['patch', 'minor', 'major'] as $bump)
                                        <label class="flex items-center gap-1 text-sm">
                                            <input type="radio" wire:model="versionBump" value="{{ $bump }}">
                                            {{ ucfirst($bump) }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex-1">
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
                            <x-filament::button wire:click="$set('editMode', false)" color="gray">Cancel</x-filament::button>
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
                                        Add New Version
                                    </x-filament::button>
                                @endif

                                {{-- Translate --}}
                                @if($canTranslate && $record->language === 'en')
                                    <x-filament::link href="{{ route('filament.app.pages.translate-lesson-plan', ['version' => $selectedVersion->id]) }}" size="sm" color="gray">
                                        Translate to Swahili
                                    </x-filament::link>
                                @endif
                            </div>
                        </div>

                        {{-- Content viewer --}}
                        <div class="prose max-w-none">
                            {!! \Illuminate\Support\Str::markdown(e($selectedVersion->content)) !!}
                        </div>
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
                        @if($aiResponse)
                            <div class="mt-3 rounded bg-gray-50 border p-3 text-sm whitespace-pre-wrap">{{ $aiResponse }}</div>
                        @endif
                        <div class="mt-3 flex gap-2">
                            <x-filament::button size="sm">Submit</x-filament::button>
                            <x-filament::button wire:click="$set('aiPanelOpen', false)" color="gray" size="sm">Close</x-filament::button>
                        </div>
                    </x-filament::section>
                @endif
            @else
                <p class="text-gray-500">No versions yet.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
