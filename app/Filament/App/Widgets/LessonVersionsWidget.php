<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Concerns\HasLessonPlanVersionTabs;
use App\Filament\App\Widgets\Concerns\HasVersionTable;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Services\VersionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\HasTabs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LessonVersionsWidget extends TableWidget
{
    use HasLessonPlanVersionTabs, HasTabs, HasVersionTable {
        HasLessonPlanVersionTabs::getTabs insteadof HasTabs;
    }

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
        $this->loadDefaultActiveTab();
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
        $this->cachedDefaultTableColumnState = null;
        $this->applyTableColumnManager();
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
            ->query(fn (): Builder => $this->buildVersionQuery())
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->modifyQueryWithActiveTab($query))
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
            ], RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkAction::make('delete')
                    ->button()
                    ->label('Delete')
                    ->color('primary')
                    ->modalHeading('Delete selected items?')
                    ->modalDescription('This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->modalSubmitAction(fn ($action) => $action->color('danger'))
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->deleteVersions($records))
                    ->deselectRecordsAfterCompletion()
                    ->extraAttributes(['x-show' => '1']),
            ])
            ->checkIfRecordIsSelectableUsing(fn (LessonPlanVersion $record): bool => ! $record->isOfficial())
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Bulk delete — delegates to shared trait implementation
    // -------------------------------------------------------------------------

    private function deleteVersions(Collection $records): void
    {
        $this->performVersionDelete($records, 'versions-deleted');
    }
}
