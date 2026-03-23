<x-filament-panels::page>
    @php
    $cards = [
        ['label' => 'Users',           'value' => $userCount,    'icon' => 'heroicon-o-users',              'color' => 'text-blue-600',   'href' => route('filament.admin.resources.users.index')],
        ['label' => 'Subjects',        'value' => $subjectCount, 'icon' => 'heroicon-o-academic-cap',       'color' => 'text-violet-600', 'href' => route('filament.admin.resources.subjects.index')],
        ['label' => 'Subject Grades',  'value' => $gradeCount,   'icon' => 'heroicon-o-table-cells',        'color' => 'text-indigo-600', 'href' => route('filament.admin.resources.subject-grades.index')],
        ['label' => 'Lesson Families', 'value' => $familyCount,  'icon' => 'heroicon-o-document-text',      'color' => 'text-emerald-600','href' => null],
        ['label' => 'Versions',        'value' => $versionCount, 'icon' => 'heroicon-o-document-duplicate', 'color' => 'text-teal-600',   'href' => null],
        ['label' => 'Official',        'value' => $officialCount,'icon' => 'heroicon-o-check-badge',        'color' => 'text-green-600',  'href' => null],
    ];
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6 mb-6">
        @foreach ($cards as $card)
        @if ($card['href'])
        <a href="{{ $card['href'] }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-gray-300 transition dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600">
        @else
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        @endif
            <div class="flex items-center gap-2 mb-3">
                <x-filament::icon :icon="$card['icon']" class="{{ $card['color'] }} h-5 w-5" />
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</p>
        @if ($card['href'])
        </a>
        @else
        </div>
        @endif
        @endforeach
    </div>

    {{-- Pending Deletion Requests — highlighted card if any are pending --}}
    <a href="{{ route('filament.admin.resources.deletion-requests.index') }}"
       class="mb-6 flex items-center justify-between rounded-xl border p-5 shadow-sm transition hover:shadow-md
              {{ $pendingDeletionCount > 0
                  ? 'border-red-200 bg-red-50 hover:border-red-300 dark:border-red-800 dark:bg-red-950 dark:hover:border-red-700'
                  : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600' }}">
        <div class="flex items-center gap-3">
            <x-filament::icon
                icon="heroicon-o-trash"
                class="h-6 w-6 {{ $pendingDeletionCount > 0 ? 'text-red-500' : 'text-gray-400' }}"
            />
            <div>
                <p class="text-sm font-semibold {{ $pendingDeletionCount > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-300' }}">
                    Deletion Requests
                </p>
                <p class="text-xs {{ $pendingDeletionCount > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ $pendingDeletionCount > 0 ? $pendingDeletionCount . ' pending — click to review' : 'No pending requests' }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($pendingDeletionCount > 0)
            <span class="inline-flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold h-7 w-7">
                {{ $pendingDeletionCount }}
            </span>
            @endif
            <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4 text-gray-400" />
        </div>
    </a>

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
            <a href="{{ route('filament.admin.resources.deletion-requests.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                Deletion Requests
            </a>
        </div>
    </div>
</x-filament-panels::page>
