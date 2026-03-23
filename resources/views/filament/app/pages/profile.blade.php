<x-filament-panels::page>
    @php $user = $this->getUser(); @endphp

    <div class="mx-auto max-w-xl">
        <x-filament::section>

            @if (!$editing)
                {{-- ── View mode ── --}}
                <dl class="divide-y divide-gray-100">
                    <div class="flex items-center py-3 gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Name</dt>
                        <dd class="text-sm text-gray-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex items-center py-3 gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Username</dt>
                        <dd class="text-sm text-gray-900">{{ $user->username }}</dd>
                    </div>
                    <div class="flex items-center py-3 gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Email address</dt>
                        <dd class="text-sm text-gray-400">{{ $user->email }}</dd>
                    </div>
                    <div class="flex items-center py-3 gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Password</dt>
                        <dd class="text-sm tracking-widest text-gray-300">••••••••</dd>
                    </div>
                    <div class="flex items-center py-3 gap-4">
                        <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Role</dt>
                        <dd class="text-sm text-gray-900">{{ $this->getRoleLabel() }}</dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-gray-100 pt-4">
                    <x-filament::button wire:click="startEditing">
                        Edit Profile
                    </x-filament::button>
                </div>

            @else
                {{-- ── Edit mode ── --}}
                <form wire:submit.prevent="saveProfile">
                    <dl class="divide-y divide-gray-100">

                        {{-- Name — editable --}}
                        <div class="flex items-start py-3 gap-4">
                            <dt class="w-40 shrink-0 pt-2 text-sm font-medium text-gray-500">Name</dt>
                            <dd class="flex-1">
                                <input
                                    wire:model="editName"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    autocomplete="name"
                                />
                                @error('editName')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </dd>
                        </div>

                        {{-- Username — read-only --}}
                        <div class="flex items-center py-3 gap-4">
                            <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Username</dt>
                            <dd class="text-sm text-gray-400">{{ $user->username }}</dd>
                        </div>

                        {{-- Email — read-only --}}
                        <div class="flex items-center py-3 gap-4">
                            <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Email address</dt>
                            <dd class="text-sm text-gray-400">{{ $user->email }}</dd>
                        </div>

                        {{-- New password --}}
                        <div class="flex items-start py-3 gap-4">
                            <dt class="w-40 shrink-0 pt-2 text-sm font-medium text-gray-500">
                                New password
                                <p class="mt-0.5 text-xs font-normal text-gray-400">Leave blank to keep current</p>
                            </dt>
                            <dd class="flex-1">
                                <input
                                    wire:model="editPassword"
                                    type="password"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                    autocomplete="new-password"
                                />
                                @error('editPassword')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </dd>
                        </div>

                        {{-- Confirm password — only when new password has a value --}}
                        @if (filled($editPassword))
                            <div class="flex items-start py-3 gap-4">
                                <dt class="w-40 shrink-0 pt-2 text-sm font-medium text-gray-500">Confirm password</dt>
                                <dd class="flex-1">
                                    <input
                                        wire:model="editPasswordConfirmation"
                                        type="password"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                        autocomplete="new-password"
                                    />
                                    @error('editPasswordConfirmation')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </dd>
                            </div>
                        @endif

                        {{-- Role — read-only --}}
                        <div class="flex items-center py-3 gap-4">
                            <dt class="w-40 shrink-0 text-sm font-medium text-gray-500">Role</dt>
                            <dd class="text-sm text-gray-400">{{ $this->getRoleLabel() }}</dd>
                        </div>

                    </dl>

                    <div class="mt-5 flex gap-3 border-t border-gray-100 pt-4">
                        <x-filament::button type="submit">
                            Save Changes
                        </x-filament::button>
                        <x-filament::button wire:click="cancelEditing" color="gray">
                            Cancel
                        </x-filament::button>
                    </div>
                </form>
            @endif

        </x-filament::section>
    </div>
</x-filament-panels::page>
