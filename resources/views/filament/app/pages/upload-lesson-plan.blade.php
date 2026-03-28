<x-filament-panels::page>

    {{-- ── Input mode toggle ──────────────────────────────────────────────────── --}}
    <x-filament::section heading="Lesson Plan Content">
        <div class="mb-4 flex gap-3">
            <x-filament::button
                wire:click="$set('inputMode', 'editor')"
                :color="$inputMode === 'editor' ? 'primary' : 'gray'"
                size="sm"
            >
                Type / Paste
            </x-filament::button>
            <x-filament::button
                wire:click="$set('inputMode', 'file')"
                :color="$inputMode === 'file' ? 'primary' : 'gray'"
                size="sm"
            >
                Upload File
            </x-filament::button>
        </div>

        @if($inputMode === 'file')
            {{-- File picker: read .md client-side into the textarea --}}
            <div x-data="{
                readFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = ev => $wire.set('content', ev.target.result);
                    reader.readAsText(file);
                    e.target.value = '';
                }
            }">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Select a <code>.md</code> file
                </label>
                <input
                    type="file"
                    accept=".md,text/plain,text/markdown"
                    @change="readFile($event)"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                @if($content)
                    <p class="mt-2 text-sm text-green-600 dark:text-green-400">
                        File loaded ({{ number_format(strlen($content)) }} characters). Review below.
                    </p>
                    <div class="mt-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Preview / edit loaded content
                        </label>
                        <textarea
                            wire:model="content"
                            rows="14"
                            class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                        ></textarea>
                    </div>
                @endif
            </div>
        @else
            {{-- Text editor / paste mode --}}
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Markdown content
            </label>
            <textarea
                wire:model="content"
                rows="20"
                placeholder="Paste or type the lesson plan content here (Markdown)…"
                class="w-full rounded-lg border border-gray-300 p-3 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
            ></textarea>
        @endif

        @error('content')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </x-filament::section>

    {{-- ── Metadata ─────────────────────────────────────────────────────────── --}}
    <x-filament::section heading="Metadata" class="mt-6">

        {{-- Subject + Grade on one row --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Subject --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                <input
                    type="text"
                    wire:model="subjectInput"
                    list="subject-list"
                    placeholder="e.g. English, Mathematics…"
                    autocomplete="off"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                <datalist id="subject-list">
                    @foreach($this->getSubjectOptions() as $name)
                        <option value="{{ $name }}">
                    @endforeach
                </datalist>
                @error('subjectInput')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Grade --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Grade</label>
                <input
                    type="text"
                    wire:model="gradeInput"
                    list="grade-list"
                    placeholder="e.g. 4"
                    autocomplete="off"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                <datalist id="grade-list">
                    @for($g = 1; $g <= 12; $g++)
                        <option value="{{ $g }}">
                    @endfor
                </datalist>
                @error('gradeInput')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Day + Contributor --}}
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Day --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Day</label>
                <input
                    type="text"
                    wire:model="dayInput"
                    list="day-list"
                    placeholder="e.g. 1"
                    autocomplete="off"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                <datalist id="day-list">
                    @for($d = 1; $d <= 30; $d++)
                        <option value="{{ $d }}">
                    @endfor
                </datalist>
                @error('dayInput')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Contributor --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Contributor username
                </label>
                <input
                    type="text"
                    wire:model="contributorInput"
                    list="user-list"
                    autocomplete="off"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                <datalist id="user-list">
                    @foreach($this->getUsernameOptions() as $uname)
                        <option value="{{ $uname }}">
                    @endforeach
                </datalist>
                @error('contributorInput')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Version fields --}}
        <div class="mt-4">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Version</label>
            <div class="flex items-center gap-2">
                @foreach([['versionMajor', 'VER'], ['versionMinor', 'Major'], ['versionPatch', 'Minor']] as [$prop, $label])
                    @unless($loop->first)
                        <span class="mt-4 text-gray-400">.</span>
                    @endunless
                    <div>
                        <p class="mb-1 text-xs text-gray-500">{{ $label }} (0–9)</p>
                        <select wire:model="{{ $prop }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @for($i = 0; $i <= 9; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                @endforeach
                <span class="mt-4 text-sm text-gray-500">
                    → v{{ $versionMajor }}.{{ $versionMinor }}.{{ $versionPatch }}
                </span>
            </div>
            @error('versionMajor')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

    </x-filament::section>

    {{-- ── Save button ─────────────────────────────────────────────────────────── --}}
    <div class="mt-6">
        <x-filament::button wire:click="savePlan" wire:loading.attr="disabled" wire:target="savePlan">
            Save This Plan
        </x-filament::button>
    </div>

</x-filament-panels::page>
