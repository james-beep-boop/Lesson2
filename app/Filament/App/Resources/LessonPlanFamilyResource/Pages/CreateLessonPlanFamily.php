<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\SubjectGrade;
use App\Services\VersionService;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateLessonPlanFamily extends Page
{
    protected static string $resource = LessonPlanFamilyResource::class;
    protected string $view = 'filament.app.pages.create-lesson-plan-family';

    public ?string $subject_grade_id = null;
    public ?string $day = null;
    public string $language = 'en';
    public string $content = '';
    public ?string $revision_note = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isSiteAdmin() || SubjectGrade::where('subject_admin_user_id', $user->id)->exists()),
            403
        );
    }

    public function save(VersionService $versionService): void
    {
        $this->validate([
            'subject_grade_id' => 'required|integer|exists:subject_grades,id',
            'day' => 'required|string',
            'language' => 'required|in:en,sw',
            'content' => 'required|string',
        ]);

        $user = auth()->user();
        $subjectGrade = SubjectGrade::findOrFail($this->subject_grade_id);

        // Subject Admin may only create in own subject_grade.
        if (! $user->isSiteAdmin()) {
            abort_unless($user->isSubjectAdminFor($subjectGrade), 403);
        }

        try {
            $version = $versionService->createFamilyWithFirstVersion(
                (int) $this->subject_grade_id,
                $this->day,
                $this->language,
                $this->content,
                $this->revision_note,
                $user
            );

            $this->redirect(
                LessonPlanFamilyResource::getUrl('view', ['record' => $version->lesson_plan_family_id])
            );
        } catch (UniqueConstraintViolationException) {
            // Family already exists — redirect to it with a prompt.
            $existing = LessonPlanFamily::where('subject_grade_id', $this->subject_grade_id)
                ->where('day', $this->day)
                ->where('language', $this->language)
                ->first();

            if ($existing) {
                Notification::make()
                    ->title('A lesson plan already exists for this subject grade, day, and language.')
                    ->body('You\'ve been redirected to the existing lesson plan. Click "Add Version" to create a new version.')
                    ->warning()
                    ->send();

                $this->redirect(
                    LessonPlanFamilyResource::getUrl('view', ['record' => $existing->id])
                );
            }
        }
    }
}
