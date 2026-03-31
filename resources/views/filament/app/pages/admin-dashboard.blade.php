<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    {{-- Hide the select-all checkbox in every table header on this page.       --}}
    {{-- Per-row checkboxes remain so users can still select rows for deletion. --}}
    <style>
        thead .fi-ta-page-checkbox { display: none; }
    </style>

    {{-- ── Stats grid (2 rows × 3 boxes) ───────────────────────────────────── --}}
    <div class="mb-6" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['siteAdmins'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">Site {{ str('Administrator')->plural($stats['siteAdmins']) }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['subjectAdmins'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">Subject {{ str('Admin')->plural($stats['subjectAdmins']) }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['editors'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">{{ str('Editor')->plural($stats['editors']) }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['totalUsers'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">{{ str('User')->plural($stats['totalUsers']) }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['families'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">Lesson {{ str('Family')->plural($stats['families']) }}</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div style="text-align: center;">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['versions'] }}</div>
                <div style="margin-top: 0.25rem; font-size: 1rem; font-weight: 600;">Lesson {{ str('Version')->plural($stats['versions']) }}</div>
            </div>
        </x-filament::section>
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

    <h2 class="fi-header-heading mb-3 mt-8">Backup and Restore</h2>

    <x-filament::section>

        {{-- ── Controls row ───────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center" style="gap: 1.25rem;">

            <x-filament::button wire:click="backupNow" wire:loading.attr="disabled" color="primary">
                <span wire:loading.remove wire:target="backupNow">Backup Now</span>
                <span wire:loading wire:target="backupNow">Creating backup…</span>
            </x-filament::button>

            @if (! empty($backups))
                <x-filament::button
                    wire:click="restoreBackup"
                    wire:loading.attr="disabled"
                    wire:confirm="This will REPLACE ALL DATA with the selected backup and log you out. Are you sure?"
                    color="info"
                >
                    <span wire:loading.remove wire:target="restoreBackup">Restore From Backup</span>
                    <span wire:loading wire:target="restoreBackup">Restoring…</span>
                </x-filament::button>

                <select
                    id="backup-select"
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
                    wire:click="deleteBackup"
                    wire:loading.attr="disabled"
                    wire:confirm="Delete this backup file? This cannot be undone."
                    color="danger"
                >
                    <span wire:loading.remove wire:target="deleteBackup">Delete Backup</span>
                    <span wire:loading wire:target="deleteBackup">Deleting…</span>
                </x-filament::button>
            @endif

        </div>

        {{-- ── Restore warning ─────────────────────────────────────────────── --}}
        @if (! empty($backups))
            <p class="text-xs text-gray-500 dark:text-gray-400" style="margin-top: 1.25rem;">
                <strong class="text-amber-600 dark:text-amber-400">Restore warning:</strong>
                Restoring will <strong>replace all current data</strong> with the chosen backup and log you out immediately.
            </p>
        @endif

    </x-filament::section>
</x-filament-panels::page>
