<x-filament-panels::page>
    @php
        $sg             = $this->getSubjectGrade();
        $editors        = $sg->users;
        $availableUsers = $this->getAvailableUsers();
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Subject Grade context --}}
        <div class="rounded-xl border border-gray-200 bg-white px-6 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">Managing editors for</p>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $sg->subject->name }} — Grade {{ $sg->grade }}
            </h2>
        </div>

        {{-- Current editors --}}
        <x-filament::section heading="Current Editors">

            @if ($editors->isEmpty())
                <p class="text-sm text-gray-400 dark:text-gray-500">No editors assigned yet.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($editors as $editor)
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $editor->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ '@'.$editor->username }}</p>
                            </div>
                            <x-filament::button
                                wire:click="removeEditor({{ $editor->id }})"
                                wire:confirm="Remove {{ $editor->name }} from this team?"
                                color="danger"
                                size="sm"
                                icon="heroicon-o-user-minus"
                            >
                                Remove
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            @endif

        </x-filament::section>

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
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ '@'.$u->username }})</option>
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
