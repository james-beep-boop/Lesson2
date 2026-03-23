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

    @if($editMode)
        {{-- Edit mode: full-width --}}
        @php $previews = $this->versionPreviews(); @endphp
        <x-filament::section heading="Edit This Plan — new version">
            <textarea
                wire:model="editContent"
                rows="28"
                class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm"
            ></textarea>

            <div class="mt-4 flex flex-wrap items-center gap-6">
                <div>
                    <label class="text-sm font-medium">Version bump</label>
                    <div class="mt-1 flex gap-4">
                        @foreach(['major', 'minor', 'patch'] as $bump)
                            <label class="flex items-center gap-1 text-sm cursor-pointer">
                                <input type="radio" wire:model.live="versionBump" value="{{ $bump }}">
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
        {{-- Normal view: sidebar + content --}}
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
                        {{-- Compare mode: diff view --}}
                        @php $diff = $this->computeDiff(); @endphp
                        <x-filament::section
                            x-data="{ layout: 'side' }"
                        >
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold">
                                        v{{ $selectedVersion->version }}
                                        <span class="text-gray-400 mx-1">vs</span>
                                        v{{ $compareVersion->version }}
                                    </span>
                                    <span class="flex items-center gap-1 text-xs">
                                        <span class="inline-block w-3 h-3 rounded bg-red-100 border border-red-200"></span> removed
                                        <span class="inline-block w-3 h-3 rounded bg-green-100 border border-green-200 ml-2"></span> added
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex rounded border border-gray-300 overflow-hidden text-xs">
                                        <button
                                            @click="layout = 'side'"
                                            :class="layout === 'side' ? 'bg-gray-200 font-semibold' : 'bg-white hover:bg-gray-50'"
                                            class="px-3 py-1"
                                        >Side by side</button>
                                        <button
                                            @click="layout = 'unified'"
                                            :class="layout === 'unified' ? 'bg-gray-200 font-semibold' : 'bg-white hover:bg-gray-50'"
                                            class="px-3 py-1 border-l border-gray-300"
                                        >Unified</button>
                                    </div>
                                    <x-filament::button wire:click="$set('compareMode', false)" color="gray" size="sm">
                                        Exit Compare
                                    </x-filament::button>
                                </div>
                            </div>

                            {{-- Side-by-side view --}}
                            <div x-show="layout === 'side'" class="overflow-auto rounded border border-gray-200">
                                <table class="w-full border-collapse font-mono text-xs leading-5">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-600 text-left">
                                            <th class="px-3 py-1 w-1/2 border-b border-r border-gray-200">v{{ $selectedVersion->version }}</th>
                                            <th class="px-3 py-1 w-1/2 border-b border-gray-200">v{{ $compareVersion->version }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($diff as $line)
                                            <tr class="align-top">
                                                <td class="px-3 py-0 border-r border-gray-100 whitespace-pre-wrap {{ $line['type'] === 'deleted' ? 'bg-red-50' : ($line['type'] === 'added' ? 'bg-gray-50' : '') }}">{{ $line['left'] !== '' ? $line['left'] : ($line['type'] === 'deleted' ? $line['left'] : "\u{00A0}") }}</td>
                                                <td class="px-3 py-0 whitespace-pre-wrap {{ $line['type'] === 'added' ? 'bg-green-50' : ($line['type'] === 'deleted' ? 'bg-gray-50' : '') }}">{{ $line['right'] !== '' ? $line['right'] : "\u{00A0}" }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Unified view --}}
                            <div x-show="layout === 'unified'" class="overflow-auto rounded border border-gray-200 font-mono text-xs leading-5">
                                @foreach($diff as $line)
                                    @if($line['type'] === 'deleted')
                                        <div class="flex bg-red-50 px-3 py-0 whitespace-pre-wrap">
                                            <span class="select-none text-red-400 mr-2 shrink-0">−</span><span>{{ $line['left'] }}</span>
                                        </div>
                                    @elseif($line['type'] === 'added')
                                        <div class="flex bg-green-50 px-3 py-0 whitespace-pre-wrap">
                                            <span class="select-none text-green-600 mr-2 shrink-0">+</span><span>{{ $line['right'] }}</span>
                                        </div>
                                    @else
                                        <div class="flex px-3 py-0 whitespace-pre-wrap text-gray-700">
                                            <span class="select-none text-gray-300 mr-2 shrink-0">&nbsp;</span><span>{{ $line['left'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
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
    @endif
</x-filament-panels::page>
