<x-filament-panels::page>
    @php
    $cards = [
        ['label' => 'Users',           'value' => $userCount,    'icon' => 'heroicon-o-users',              'color' => 'text-blue-600'],
        ['label' => 'Subjects',        'value' => $subjectCount, 'icon' => 'heroicon-o-academic-cap',       'color' => 'text-violet-600'],
        ['label' => 'Subject Grades',  'value' => $gradeCount,   'icon' => 'heroicon-o-table-cells',        'color' => 'text-indigo-600'],
        ['label' => 'Lesson Families', 'value' => $familyCount,  'icon' => 'heroicon-o-document-text',      'color' => 'text-emerald-600'],
        ['label' => 'Versions',        'value' => $versionCount, 'icon' => 'heroicon-o-document-duplicate', 'color' => 'text-teal-600'],
        ['label' => 'Official',        'value' => $officialCount,'icon' => 'heroicon-o-check-badge',        'color' => 'text-green-600'],
    ];
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6 mb-8">
        @foreach ($cards as $card)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-2 mb-3">
                <x-filament::icon :icon="$card['icon']" class="{{ $card['color'] }} h-5 w-5" />
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Quick links</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('filament.admin.resources.users.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                <x-filament::icon icon="heroicon-o-users" class="h-4 w-4" />
                Manage Users
            </a>
            <a href="{{ route('filament.admin.resources.subject-grades.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                <x-filament::icon icon="heroicon-o-table-cells" class="h-4 w-4" />
                Subject Grades
            </a>
        </div>
    </div>
</x-filament-panels::page>
