<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    {{-- Hide the select-all checkbox in every table header on this page.       --}}
    {{-- Per-row checkboxes remain so users can still select rows for deletion. --}}
    <style>
        thead .fi-ta-page-checkbox { display: none; }
    </style>

    {{-- ── Stats bar ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-gray-200 bg-white px-5 py-3 text-sm dark:border-white/10 dark:bg-white/5">
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['siteAdmins'] }}</span> <span class="text-gray-500 dark:text-gray-400">Site {{ str('Administrator')->plural($stats['siteAdmins']) }}</span></span>
        <span class="text-gray-300 dark:text-gray-600">·</span>
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['subjectAdmins'] }}</span> <span class="text-gray-500 dark:text-gray-400">Subject {{ str('Admin')->plural($stats['subjectAdmins']) }}</span></span>
        <span class="text-gray-300 dark:text-gray-600">·</span>
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['editors'] }}</span> <span class="text-gray-500 dark:text-gray-400">{{ str('Editor')->plural($stats['editors']) }}</span></span>
        <span class="text-gray-300 dark:text-gray-600">·</span>
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['totalUsers'] }}</span> <span class="text-gray-500 dark:text-gray-400">{{ str('User')->plural($stats['totalUsers']) }}</span></span>

        <span class="mx-2 h-4 w-px bg-gray-300 dark:bg-gray-600"></span>

        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['families'] }}</span> <span class="text-gray-500 dark:text-gray-400">{{ str('Family')->plural($stats['families']) }}</span></span>
        <span class="text-gray-300 dark:text-gray-600">·</span>
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ $stats['versions'] }}</span> <span class="text-gray-500 dark:text-gray-400">{{ str('Version')->plural($stats['versions']) }}</span></span>
    </div>

    {{-- ── Lesson Plans Admin section ──────────────────────────────────────────── --}}
    <div class="mb-3 flex items-center justify-between">
        <h2 class="fi-header-heading">Lesson Plans Admin</h2>
        <x-filament::button
            tag="a"
            href="{{ \App\Filament\App\Resources\LessonPlanFamilyResource::getUrl('create') }}"
        >
            Add Lesson Plan
        </x-filament::button>
    </div>

    @livewire(\App\Filament\App\Widgets\LessonVersionsWidget::class)

    {{-- ── User Admin section ──────────────────────────────────────────────────── --}}
    <h2 class="fi-header-heading mb-3 mt-8">User Admin</h2>

    @livewire(\App\Filament\App\Widgets\UsersWidget::class)

    {{-- ── Backup & Restore section ────────────────────────────────────────────── --}}
    @php
        $backups = $this->getAvailableBackups();
    @endphp

    <div class="mt-10 rounded-lg border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">

        {{-- ── Header row: heading + Backup Now + restore controls ──────────── --}}
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="fi-header-heading mr-auto">Backup &amp; Restore</h2>

            <x-filament::button wire:click="backupNow" wire:loading.attr="disabled" color="primary">
                <span wire:loading.remove wire:target="backupNow">Backup Now</span>
                <span wire:loading wire:target="backupNow">Creating backup…</span>
            </x-filament::button>

            @if (! empty($backups))
                <select
                    id="restore-select"
                    wire:model="restoreFilename"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
                >
                    <option value="">— choose a backup —</option>
                    @foreach ($backups as $backup)
                        <option value="{{ $backup['filename'] }}">
                            {{ $backup['filename'] }} ({{ number_format($backup['size'] / 1024, 1) }} KB)
                        </option>
                    @endforeach
                </select>

                <x-filament::button
                    wire:click="restoreBackup"
                    wire:loading.attr="disabled"
                    wire:confirm="This will REPLACE ALL DATA with the selected backup and log you out. Are you sure?"
                    color="danger"
                >
                    <span wire:loading.remove wire:target="restoreBackup">Restore From Backup</span>
                    <span wire:loading wire:target="restoreBackup">Restoring…</span>
                </x-filament::button>
            @endif
        </div>

        {{-- ── Warning note (only shown when there are backups to restore) ──── --}}
        @if (! empty($backups))
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <strong class="text-red-600 dark:text-red-400">Restore warning:</strong>
                Restoring will <strong>replace all current data</strong> with the chosen backup and log you out immediately.
            </p>
        @endif

    </div>
</x-filament-panels::page>
