<x-filament-widgets::widget class="fi-wi-table">
    <x-filament::tabs class="w-full px-4 pt-4 justify-center">
        @foreach ($this->getCachedTabs() as $key => $tab)
            <x-filament::tabs.item
                wire:click="$set('activeTab', '{{ $key }}')"
                :active="$activeTab === $key"
            >{{ $tab->getLabel() }}</x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table ?? null }}
</x-filament-widgets::widget>
