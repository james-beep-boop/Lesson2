<x-filament-widgets::widget class="fi-wi-table">
    <div class="fi-sc-tabs py-3">
        <x-filament::tabs>
            @foreach ($this->getCachedTabs() as $key => $tab)
                <x-filament::tabs.item
                    wire:click="$set('activeTab', '{{ $key }}')"
                    :active="$activeTab === $key"
                >{{ $tab->getLabel() }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    {{ $this->table ?? null }}
</x-filament-widgets::widget>
