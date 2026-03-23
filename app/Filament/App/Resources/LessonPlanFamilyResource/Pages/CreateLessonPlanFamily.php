<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\SubjectGrade;
use App\Services\VersionService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateLessonPlanFamily extends CreateRecord
{
    protected static string $resource = LessonPlanFamilyResource::class;

    // No "Save & Create Another" needed for lesson plans
    protected static bool $canCreateAnother = false;

    /**
     * Extend the resource form with content and revision note fields.
     * These two fields are only used during creation (content lives on the
     * version, not the family) and are extracted in handleRecordCreation().
     */
    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        // Subject Admins may only create within their own subject_grade.
        // Site Admins see all subject_grades.
        $subjectGradeQuery = fn ($query) => $user->isSiteAdmin()
            ? $query->with('subject')->orderBy('grade')
            : $query->with('subject')
                ->where('subject_admin_user_id', $user->id)
                ->orderBy('grade');

        return $schema->schema([
            Select::make('subject_grade_id')
                ->label('Subject Grade')
                ->options(
                    SubjectGrade::query()
                        ->when(
                            ! $user->isSiteAdmin(),
                            fn ($q) => $q->where('subject_admin_user_id', $user->id)
                        )
                        ->with('subject')
                        ->get()
                        ->mapWithKeys(fn ($sg) => [$sg->id => $sg->subject->name . ' — Grade ' . $sg->grade])
                )
                ->required()
                ->searchable(),

            TextInput::make('day')
                ->label('Day')
                ->required()
                ->placeholder('e.g. 1'),

            Select::make('language')
                ->label('Language')
                ->options(['en' => 'English', 'sw' => 'Swahili'])
                ->default('en')
                ->required(),

            Textarea::make('content')
                ->label('Lesson Plan Content (Markdown)')
                ->rows(20)
                ->required()
                ->columnSpanFull()
                ->placeholder('Write your lesson plan in Markdown...'),

            TextInput::make('revision_note')
                ->label('Revision Note (optional)')
                ->placeholder('Brief note about this version')
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $subjectGrade = SubjectGrade::findOrFail($data['subject_grade_id']);

        // Double-check Subject Admin scope (canCreate() already gated top-level access)
        if (! $user->isSiteAdmin()) {
            abort_unless($user->isSubjectAdminFor($subjectGrade), 403);
        }

        try {
            $version = app(VersionService::class)->createFamilyWithFirstVersion(
                (int) $data['subject_grade_id'],
                $data['day'],
                $data['language'],
                $data['content'],
                $data['revision_note'] ?? null,
                $user
            );

            // Store the family id so getRedirectUrl() can build the view URL
            $this->createdFamilyId = $version->lesson_plan_family_id;

            return LessonPlanFamily::findOrFail($version->lesson_plan_family_id);

        } catch (UniqueConstraintViolationException) {
            $existing = LessonPlanFamily::where('subject_grade_id', $data['subject_grade_id'])
                ->where('day', $data['day'])
                ->where('language', $data['language'])
                ->first();

            if ($existing) {
                Notification::make('duplicate-family')
                    ->title('A lesson plan already exists for this subject grade, day, and language.')
                    ->body('You\'ve been redirected to the existing lesson plan. Use "Edit This Plan" to add a new version.')
                    ->warning()
                    ->persistent()
                    ->send();

                $this->redirect(LessonPlanFamilyResource::getUrl('view', ['record' => $existing->id]));
            }

            throw new \RuntimeException('Family already exists but could not be found.');
        }
    }

    protected function getRedirectUrl(): string
    {
        return LessonPlanFamilyResource::getUrl('view', ['record' => $this->record->getKey()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lesson plan created.';
    }
}
