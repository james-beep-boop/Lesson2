<?php

namespace App\Filament\App\Pages;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UploadLessonPlan extends Page
{
    protected string $view = 'filament.app.pages.upload-lesson-plan';

    protected static ?string $title = 'Upload Lesson Plan';

    protected static bool $shouldRegisterNavigation = false;

    // Content input mode
    public string $inputMode = 'editor';

    public string $content = '';

    // Metadata
    public string $subjectInput = '';

    public string $gradeInput = '';

    public string $dayInput = '';

    public string $versionMajor = '1';

    public string $versionMinor = '0';

    public string $versionPatch = '0';

    public string $contributorInput = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSiteAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        $this->contributorInput = auth()->user()->username;
    }

    public function savePlan(): void
    {
        $this->validate([
            'content' => ['required', 'min:10'],
            'subjectInput' => ['required', 'min:1', 'max:100'],
            'gradeInput' => ['required', 'integer', 'min:1', 'max:99'],
            'dayInput' => ['required', 'integer', 'min:1', 'max:99'],
            'versionMajor' => ['required', 'integer', 'min:0', 'max:9'],
            'versionMinor' => ['required', 'integer', 'min:0', 'max:9'],
            'versionPatch' => ['required', 'integer', 'min:0', 'max:9'],
            'contributorInput' => ['required'],
        ]);

        $contributor = User::where('username', $this->contributorInput)
            ->where('is_system', false)
            ->first();

        if (! $contributor) {
            $this->addError('contributorInput', 'No user found with that username.');

            return;
        }

        $versionString = "{$this->versionMajor}.{$this->versionMinor}.{$this->versionPatch}";

        try {
            $savedLabel = DB::transaction(function () use ($contributor, $versionString): string {
                $subject = Subject::firstOrCreate(['name' => trim($this->subjectInput)]);

                $subjectGrade = SubjectGrade::firstOrCreate([
                    'subject_id' => $subject->id,
                    'grade' => (int) $this->gradeInput,
                ]);

                $family = LessonPlanFamily::firstOrCreate([
                    'subject_grade_id' => $subjectGrade->id,
                    'day' => $this->dayInput,
                ]);

                $version = new LessonPlanVersion([
                    'lesson_plan_family_id' => $family->id,
                    'content' => $this->content,
                    'revision_note' => null,
                ]);
                $version->contributor_id = $contributor->id;
                $version->version = $versionString;
                $version->save();

                return "{$subject->name} — Grade {$subjectGrade->grade} · Day {$family->day} · v{$versionString}";
            });
        } catch (QueryException $e) {
            $isDuplicate = str_contains($e->getMessage(), 'UNIQUE constraint failed')
                || $e->getCode() === '23000';

            if ($isDuplicate) {
                $this->addError('versionMajor', "Version {$versionString} already exists for this lesson plan.");

                return;
            }

            throw $e;
        }

        Notification::make('plan-saved')
            ->title('Lesson plan saved')
            ->body($savedLabel)
            ->success()
            ->send();

        $this->reset(['content', 'subjectInput', 'gradeInput', 'dayInput', 'versionMajor', 'versionMinor', 'versionPatch']);
        $this->contributorInput = auth()->user()->username;
    }

    /** @return array<string, string> */
    public function getSubjectOptions(): array
    {
        return Subject::orderBy('name')->pluck('name', 'name')->all();
    }

    /** @return array<string, string> */
    public function getUsernameOptions(): array
    {
        return User::where('is_system', false)
            ->orderBy('username')
            ->pluck('username', 'username')
            ->all();
    }
}
