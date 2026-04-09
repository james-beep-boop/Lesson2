<x-filament-panels::page>
    @php
        $availableUsers = $this->getAvailableUsers();
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Current editors --}}
        {{ $this->table }}

        {{-- Add editor --}}
        <x-filament::section heading="Add Editor">

            @if ($availableUsers->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">All users are already on this team.</p>
            @else
                <form wire:submit.prevent="addEditor" class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Select user
                        </label>
                        <select
                            wire:model="addUserId"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">— Choose a user —</option>
                            @foreach ($availableUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option>
                            @endforeach
                        </select>
                        @error('addUserId')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-user-plus"
                        wire:loading.attr="disabled"
                        wire:target="addEditor"
                    >
                        Add Editor
                    </x-filament::button>
                </form>
            @endif

        </x-filament::section>

    </div>
</x-filament-panels::page>
