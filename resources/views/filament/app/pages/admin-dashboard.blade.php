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
            href="{{ \App\Filament\App\Pages\UploadLessonPlan::getUrl() }}"
        >
            Add Lesson Plan
        </x-filament::button>
    </div>

    @livewire(\App\Filament\App\Widgets\LessonVersionsWidget::class)

    {{-- ── User Admin section ──────────────────────────────────────────────────── --}}
    <h2 class="fi-header-heading mb-3 mt-8">User Admin</h2>

    @livewire(\App\Filament\App\Widgets\UsersWidget::class)
</x-filament-panels::page>
