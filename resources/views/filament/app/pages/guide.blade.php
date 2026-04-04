<x-filament-panels::page>
    <div class="max-w-2xl">
        {{-- Language toggle --}}
        <div class="mb-6 flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Language:</span>
            <x-filament::button
                wire:click="switchLanguage('en')"
                :color="$language === 'en' ? 'primary' : 'gray'"
                size="sm"
            >
                English
            </x-filament::button>
            <x-filament::button
                wire:click="switchLanguage('sw')"
                :color="$language === 'sw' ? 'primary' : 'gray'"
                size="sm"
            >
                Swahili / Kiswahili
            </x-filament::button>
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
