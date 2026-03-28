<x-filament-widgets::widget class="fi-wi-table">
    <x-filament::tabs class="px-4 pt-4 justify-center">
        @foreach (['all' => 'All', 'official' => 'Official', 'latest' => 'Latest', 'favorites' => 'Favorites'] as $tab => $label)
            <x-filament::tabs.item
                wire:click="$set('activeTab', '{{ $tab }}')"
                :active="$activeTab === $tab"
            >{{ $label }}</x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table ?? null }}
</x-filament-widgets::widget>
