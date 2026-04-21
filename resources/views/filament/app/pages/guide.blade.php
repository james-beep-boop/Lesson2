<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Role-specific orientation heading --}}
        @if($orientation = $this->orientationText())
            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100">
                @markdown($orientation)
            </div>
        @endif

        {{-- Language toggle --}}
        <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
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
                <x-filament::button
                    tag="a"
                    :href="$this->manualDownloadUrl()"
                    color="gray"
                >
                    Download Manual
                </x-filament::button>
            </div>
        </div>

        {{-- Guide sections --}}
        <div class="space-y-4">
            @foreach($this->sections() as $section)
                <x-filament::section :heading="$section['title']" collapsible collapsed>
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        @markdown($section['body'])
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
