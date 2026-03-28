<x-filament-widgets::widget class="fi-wi-table">
    {{-- Filter tabs — same style as the Lessons list page tabs --}}
    <div class="flex flex-wrap items-center gap-1 px-4 pt-4">
        @foreach (['all' => 'All', 'official' => 'Official', 'latest' => 'Latest', 'favorites' => 'Favorites'] as $tab => $label)
            <button
                wire:click="$set('activeTab', '{{ $tab }}')"
                @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus:outline-none',
                    'bg-white shadow ring-1 ring-gray-950/5 text-gray-950 dark:bg-white/5 dark:ring-white/10 dark:text-white' => $activeTab === $tab,
                    'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200' => $activeTab !== $tab,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{ $this->table ?? null }}

    <x-filament-actions::modals />
</x-filament-widgets::widget>
