<x-filament-panels::page>
    @php
        $stats    = $this->getStats();
        $versions = $this->getLessonVersions();
        $users    = $this->getUsers();
        $hasVersionsSelected = ! empty($selectedVersionIds);
        $hasUsersSelected    = ! empty($selectedUserIds);
        $hasStatusChanges    = $this->hasStatusChanges();
    @endphp

    {{-- ── Stats bar ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-gray-200 bg-white px-5 py-3 text-sm">
        <span><span class="font-semibold text-gray-900">{{ $stats['siteAdmins'] }}</span> <span class="text-gray-500">Site {{ str('Administrator')->plural($stats['siteAdmins']) }}</span></span>
        <span class="text-gray-300">·</span>
        <span><span class="font-semibold text-gray-900">{{ $stats['subjectAdmins'] }}</span> <span class="text-gray-500">Subject {{ str('Admin')->plural($stats['subjectAdmins']) }}</span></span>
        <span class="text-gray-300">·</span>
        <span><span class="font-semibold text-gray-900">{{ $stats['editors'] }}</span> <span class="text-gray-500">{{ str('Editor')->plural($stats['editors']) }}</span></span>
        <span class="text-gray-300">·</span>
        <span><span class="font-semibold text-gray-900">{{ $stats['totalUsers'] }}</span> <span class="text-gray-500">{{ str('User')->plural($stats['totalUsers']) }}</span></span>

        <span class="mx-2 h-4 w-px bg-gray-300"></span>

        <span><span class="font-semibold text-gray-900">{{ $stats['families'] }}</span> <span class="text-gray-500">{{ str('Family')->plural($stats['families']) }}</span></span>
        <span class="text-gray-300">·</span>
        <span><span class="font-semibold text-gray-900">{{ $stats['versions'] }}</span> <span class="text-gray-500">{{ str('Version')->plural($stats['versions']) }}</span></span>
    </div>

    {{-- ── Lesson Plans section ───────────────────────────────────────────────── --}}
    <div class="mb-2 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-900">Lesson Plans</h2>
        <x-filament::button
            tag="a"
            href="{{ \App\Filament\App\Resources\LessonPlanFamilyResource::getUrl('create') }}"
        >
            Add Lesson Plan
        </x-filament::button>
    </div>

    <x-filament::section>
        {{-- Tab bar + search --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                @foreach (['all' => 'All', 'official' => 'Official', 'latest' => 'Latest', 'favorites' => 'Favorites'] as $tab => $label)
                    <button
                        wire:click="setLessonTab('{{ $tab }}')"
                        class="rounded-md px-3 py-1 font-medium transition-colors {{ $lessonTab === $tab ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="lessonSearch"
                placeholder="Search subject, grade, day…"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 w-56"
            >
        </div>

        {{-- Lesson plan table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3 w-8">
                            <span class="sr-only">Delete</span>
                        </th>
                        <th class="py-2 pr-4">Subject</th>
                        <th class="py-2 pr-4">Grade</th>
                        <th class="py-2 pr-4">Day</th>
                        <th class="py-2 pr-4">Version</th>
                        <th class="py-2 pr-4">Official</th>
                        <th class="py-2 pr-4">By</th>
                        <th class="py-2">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($versions as $version)
                        @php
                            $isOfficial = $version->family
                                && (int) $version->family->official_version_id === $version->id;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 pr-3">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedVersionIds"
                                    value="{{ $version->id }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                >
                            </td>
                            <td class="py-2 pr-4 font-medium text-gray-900">
                                {{ $version->family?->subjectGrade?->subject?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-600">
                                Grade {{ $version->family?->subjectGrade?->grade ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-600">
                                {{ $version->family?->day ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-600">
                                v{{ $version->version }}
                            </td>
                            <td class="py-2 pr-4">
                                <button
                                    wire:click="setOfficial({{ $version->lesson_plan_family_id }}, {{ $version->id }})"
                                    title="{{ $isOfficial ? 'Click to remove official status' : 'Click to set as official' }}"
                                    class="rounded px-2 py-0.5 text-xs font-semibold transition-colors {{ $isOfficial ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600' }}"
                                >{{ $isOfficial ? '✓ Official' : 'Set Official' }}</button>
                            </td>
                            <td class="py-2 pr-4 text-gray-600">
                                {{ $version->contributor?->name ?? '—' }}
                            </td>
                            <td class="py-2 text-gray-500">
                                {{ $version->created_at?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-400">No lesson plans found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Delete Now button with two-click confirmation --}}
        <div x-data="{ confirm: false }" class="mt-4 flex items-center gap-3">
            <button
                x-show="! confirm"
                @click="confirm = true"
                @disabled(! $hasVersionsSelected)
                class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors {{ $hasVersionsSelected ? 'bg-primary-600 text-white hover:bg-primary-700 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}"
            >Delete Now</button>
            <template x-if="confirm">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-red-700">Delete {{ count($selectedVersionIds) }} {{ str('item')->plural(count($selectedVersionIds)) }}? This cannot be undone.</span>
                    <button
                        wire:click="deleteSelectedVersions"
                        @click="confirm = false"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
                    >Yes, delete</button>
                    <button
                        @click="confirm = false"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                    >Cancel</button>
                </div>
            </template>
        </div>
    </x-filament::section>

    {{-- ── Users section ─────────────────────────────────────────────────────── --}}
    <h2 class="mb-2 mt-8 text-lg font-bold text-gray-900">Users</h2>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3 w-8">
                            <span class="sr-only">Delete</span>
                        </th>
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50 {{ $user->id === auth()->id() ? 'bg-blue-50' : '' }}">
                            <td class="py-2 pr-3">
                                @if ($user->id !== auth()->id())
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedUserIds"
                                        value="{{ $user->id }}"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    >
                                @else
                                    <span title="Cannot delete your own account" class="text-gray-300 cursor-not-allowed select-none">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 font-medium text-gray-900">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="ml-1 text-xs text-gray-400">(you)</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                <select
                                    wire:model.live="userStatusChanges.{{ $user->id }}"
                                    class="rounded border-gray-300 py-0.5 text-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option value="administrator">Administrator</option>
                                    <option value="user">User</option>
                                </select>
                            </td>
                            <td class="py-2 text-gray-600">{{ $user->email }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-400">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Action buttons --}}
        <div class="mt-4 flex flex-wrap items-center gap-3">
            {{-- User deletion — two-click confirmation --}}
            <div x-data="{ confirm: false }" class="flex items-center gap-2">
                <button
                    x-show="! confirm"
                    @click="confirm = true"
                    @disabled(! $hasUsersSelected)
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors {{ $hasUsersSelected ? 'bg-primary-600 text-white hover:bg-primary-700 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}"
                >Confirm Deletion</button>
                <template x-if="confirm">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-red-700">Delete {{ count($selectedUserIds) }} {{ str('user')->plural(count($selectedUserIds)) }}? This cannot be undone.</span>
                        <button
                            wire:click="deleteSelectedUsers"
                            @click="confirm = false"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
                        >Yes, delete</button>
                        <button
                            @click="confirm = false"
                            class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                        >Cancel</button>
                    </div>
                </template>
            </div>

            <button
                wire:click="confirmStatusChanges"
                @disabled(! $hasStatusChanges)
                class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors {{ $hasStatusChanges ? 'bg-primary-600 text-white hover:bg-primary-700 cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}"
            >Confirm Status Changes</button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
