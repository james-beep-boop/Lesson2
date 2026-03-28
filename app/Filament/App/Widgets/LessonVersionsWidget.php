<?php

namespace App\Filament\App\Widgets;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\VersionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LessonVersionsWidget extends TableWidget
{
    /** Active filter tab: all | official | latest | favorites */
    public string $activeTab = 'all';

    /**
     * Custom view renders the tab bar above the Filament table.
     *
     * @var view-string
     */
    protected string $view = 'filament.app.widgets.lesson-versions-widget';

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    /**
     * Enforce site-admin access at mount time.
     * Widgets are standalone Livewire components; their methods are reachable
     * via HTTP independently of the parent page's abort_unless guard.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);
    }

    // -------------------------------------------------------------------------
    // Heading
    // -------------------------------------------------------------------------

    /** Return empty string so TableWidget::makeTable() sets no visible heading. */
    protected function getTableHeading(): string|Htmlable|null
    {
        return '';
    }

    // -------------------------------------------------------------------------
    // Table definition
    // -------------------------------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildQuery())
            ->queryStringIdentifier('versions')
            ->columns([
                TextColumn::make('family.subjectGrade.subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('family.subjectGrade.grade')
                    ->label('Grade')
                    ->formatStateUsing(fn ($state) => 'Grade '.$state)
                    ->sortable(),
                TextColumn::make('family.day')
                    ->label('Day')
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('contributor.name')
                    ->label('By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('toggleOfficial')
                    ->label(fn (LessonPlanVersion $record): string => ($record->family && (int) $record->family->official_version_id === $record->id)
                        ? '✓ Official'
                        : 'Set Official'
                    )
                    ->color(fn (LessonPlanVersion $record): string => ($record->family && (int) $record->family->official_version_id === $record->id)
                        ? 'success'
                        : 'gray'
                    )
                    ->tooltip(fn (LessonPlanVersion $record): string => ($record->family && (int) $record->family->official_version_id === $record->id)
                        ? 'Remove official status from this version'
                        : 'Mark this version as the official one for this plan'
                    )
                    ->button()
                    ->size('xs')
                    ->action(function (LessonPlanVersion $record): void {
                        abort_unless(auth()->user()?->isSiteAdmin(), 403);

                        // Reload family fresh to avoid acting on a stale official_version_id.
                        $family = $record->lesson_plan_family_id
                            ? LessonPlanFamily::find($record->lesson_plan_family_id)
                            : null;

                        if (! $family) {
                            return;
                        }

                        $isCurrentlyOfficial = (int) $family->official_version_id === $record->id;

                        app(VersionService::class)->setOfficialVersion(
                            $family,
                            $isCurrentlyOfficial ? null : $record,
                        );

                        Notification::make('official-updated')
                            ->title($isCurrentlyOfficial ? 'Official status removed.' : 'Official version set.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(fn (Collection $records) => $this->deleteVersions($records))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Query builder
    // -------------------------------------------------------------------------

    private function buildQuery(): Builder
    {
        $query = LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor'])
            ->join('lesson_plan_families', 'lesson_plan_versions.lesson_plan_family_id', '=', 'lesson_plan_families.id')
            ->join('subject_grades', 'lesson_plan_families.subject_grade_id', '=', 'subject_grades.id')
            ->join('subjects', 'subject_grades.subject_id', '=', 'subjects.id')
            ->select('lesson_plan_versions.*');

        match ($this->activeTab) {
            'official' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_families')
                    ->whereNotNull('official_version_id')
                    ->pluck('official_version_id')
            ),
            'latest' => $query->whereIn(
                'lesson_plan_versions.id',
                DB::table('lesson_plan_versions')
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('lesson_plan_family_id')
            ),
            'favorites' => $query->whereHas(
                'favorites',
                fn ($q) => $q->where('user_id', auth()->id())
            ),
            default => null,
        };

        return $query;
    }

    // -------------------------------------------------------------------------
    // Tab change — reset pagination and deselect
    // -------------------------------------------------------------------------

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    // -------------------------------------------------------------------------
    // Bulk delete
    // -------------------------------------------------------------------------

    private function deleteVersions(Collection $records): void
    {
        abort_unless(auth()->user()?->isSiteAdmin(), 403);

        DB::transaction(function () use ($records): void {
            foreach ($records as $version) {
                // Load a fresh family instance to avoid acting on a stale
                // official_version_id that may have changed since the table was rendered.
                $family = $version->lesson_plan_family_id
                    ? LessonPlanFamily::find($version->lesson_plan_family_id)
                    : null;

                // Clear official pointer before deleting to avoid FK issues.
                if ($family && (int) $family->official_version_id === $version->id) {
                    $family->official_version_id = null;
                    $family->save();
                }

                // Favorites cascade via FK; deletion_requests nullOnDelete.
                $version->delete();

                // Remove orphaned family.
                if ($family && $family->versions()->doesntExist()) {
                    $family->delete();
                }
            }
        });

        $count = $records->count();

        Notification::make('versions-deleted')
            ->title('Deleted '.$count.' '.str('version')->plural($count).'.')
            ->success()
            ->send();
    }
}
