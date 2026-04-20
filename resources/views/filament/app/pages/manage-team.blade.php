<x-filament-panels::page>
    @php
        $subjectGrades = $this->getSubjectGrades();
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <x-filament::section>
            <div class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    You are the subject administrator for:
                </p>

                <ul class="space-y-2">
                    @foreach ($subjectGrades as $subjectGrade)
                        <li class="rounded-lg border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-gray-100">
                            {{ $subjectGrade->subject->name }}, Grade {{ $subjectGrade->grade }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-filament::section>

        @foreach ($subjectGrades as $subjectGrade)
            <livewire:subject-grade-team-manager
                :subject-grade-id="$subjectGrade->id"
                :key="'subject-grade-team-manager-'.$subjectGrade->id"
            />
        @endforeach
    </div>
</x-filament-panels::page>
