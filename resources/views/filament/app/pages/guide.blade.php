<x-filament-panels::page>
    <div class="max-w-2xl">
        {{-- Language toggle --}}
        <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="flex flex-wrap gap-3">
                <x-filament::button
                    wire:click="switchLanguage('en')"
                    :color="$language === 'en' ? 'primary' : 'gray'"
                >
                    English
                </x-filament::button>
                <x-filament::button
                    wire:click="switchLanguage('sw')"
                    :color="$language === 'sw' ? 'primary' : 'gray'"
                >
                    Swahili
                </x-filament::button>
            </div>
        </div>

        {{-- Guide sections --}}
        <div class="space-y-4">
            @foreach($this->sections() as $section)
                <x-filament::section :heading="$section['title']" collapsible>
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        @markdown($section['body'])
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
