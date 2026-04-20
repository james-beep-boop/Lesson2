<?php

namespace App\Livewire;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\SubjectAdminService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SubjectGradeTeamManager extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?int $addUserId = null;

    public int $subjectGradeId;

    private ?SubjectGrade $cachedSubjectGrade = null;

    public function mount(int $subjectGradeId): void
    {
        $subjectGrade = SubjectGrade::query()
            ->with(['subject', 'users'])
            ->findOrFail($subjectGradeId);

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $this->subjectGradeId = $subjectGradeId;
        $this->cachedSubjectGrade = $subjectGrade;
    }

    public function getSubjectGrade(): SubjectGrade
    {
        return $this->cachedSubjectGrade ??= SubjectGrade::query()
            ->with(['subject', 'users'])
            ->findOrFail($this->subjectGradeId);
    }

    public function getAvailableUsers(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'username'])
            ->where('is_system', false)
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $this->getExcludedUserIds())
            ->orderBy('name')
            ->get();
    }

    public function addEditor(): void
    {
        $subjectGrade = $this->getSubjectGrade();

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $this->validate([
            'addUserId' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('is_system', false)
                        ->whereNotNull('email_verified_at')),
                Rule::notIn($this->getExcludedUserIds()),
            ],
        ], [
            'addUserId.not_in' => 'The selected user is already assigned to this subject-grade.',
        ]);

        $user = User::query()
            ->where('is_system', false)
            ->whereNotNull('email_verified_at')
            ->findOrFail($this->addUserId);

        app(SubjectAdminService::class)->assignEditor($user, $subjectGrade);

        $this->addUserId = null;

        Notification::make('editor-added')
            ->title("{$user->name} added as Editor.")
            ->success()
            ->send();
    }

    public function removeEditor(int $userId): void
    {
        $subjectGrade = $this->getSubjectGrade();

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $user = User::findOrFail($userId);

        app(SubjectAdminService::class)->removeUser($user, $subjectGrade);

        Notification::make('editor-removed')
            ->title("{$user->name} removed from {$subjectGrade->subject->name}, Grade {$subjectGrade->grade}.")
            ->success()
            ->send();
    }

    public function removeSelectedEditors(): void
    {
        $subjectGrade = $this->getSubjectGrade();

        abort_unless(auth()->user()?->isSubjectAdminFor($subjectGrade), 403);

        $users = User::findMany($this->selectedTableRecords);

        if ($users->isEmpty()) {
            return;
        }

        $service = app(SubjectAdminService::class);

        foreach ($users as $user) {
            $service->removeUser($user, $subjectGrade);
        }

        $count = $users->count();
        $this->selectedTableRecords = [];

        Notification::make('editors-removed')
            ->title($count === 1
                ? "{$users->first()->name} removed from {$subjectGrade->subject->name}, Grade {$subjectGrade->grade}."
                : "{$count} editors removed from {$subjectGrade->subject->name}, Grade {$subjectGrade->grade}.")
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        $subjectGrade = $this->getSubjectGrade();

        return $table
            ->query($this->getEditorsQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Full name'),
                TextColumn::make('username')
                    ->label('Username'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('edits_count')
                    ->label('Edits')
                    ->numeric(),
                TextColumn::make('last_edit_at')
                    ->label('Last Edit')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Never'),
            ])
            ->toolbarActions([
                Action::make('removeSelected')
                    ->label('Remove')
                    ->color('danger')
                    ->icon('heroicon-o-user-minus')
                    ->requiresConfirmation()
                    ->modalHeading('Remove editors')
                    ->modalDescription("Remove the selected editors from {$subjectGrade->subject->name}, Grade {$subjectGrade->grade}?")
                    ->disabled(fn (): bool => empty($this->selectedTableRecords))
                    ->action(function (): void {
                        $this->removeSelectedEditors();
                    }),
            ])
            ->selectable(true)
            ->heading("Current Editors of {$subjectGrade->subject->name}, Grade {$subjectGrade->grade}")
            ->emptyStateHeading('No editors assigned yet.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->paginated(false)
            ->queryStringIdentifier("subject-grade-team-{$this->subjectGradeId}");
    }

    public function render(): View
    {
        return view('livewire.subject-grade-team-manager');
    }

    private function getEditorsQuery(): Builder
    {
        return User::query()
            ->whereHas('subjectGrades', fn (Builder $query) => $query->where('subject_grades.id', $this->subjectGradeId))
            ->addSelect([
                'edits_count' => LessonPlanVersion::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('contributor_id', 'users.id')
                    ->whereIn('lesson_plan_family_id', LessonPlanFamily::select('id')->where('subject_grade_id', $this->subjectGradeId)),
                'last_edit_at' => LessonPlanVersion::query()
                    ->selectRaw('max(created_at)')
                    ->whereColumn('contributor_id', 'users.id')
                    ->whereIn('lesson_plan_family_id', LessonPlanFamily::select('id')->where('subject_grade_id', $this->subjectGradeId)),
            ]);
    }

    private function getExcludedUserIds(): array
    {
        $subjectGrade = $this->getSubjectGrade();

        return $subjectGrade->users->pluck('id')
            ->push($subjectGrade->subject_admin_user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
