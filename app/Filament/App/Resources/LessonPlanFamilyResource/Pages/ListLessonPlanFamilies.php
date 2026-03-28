<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Concerns\HasLessonPlanVersionTabs;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanVersion;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLessonPlanFamilies extends ListRecords
{
    use HasLessonPlanVersionTabs;

    protected static string $resource = LessonPlanFamilyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Query versions instead of families so each table row is one LessonPlanVersion.
     * Columns, filters, and recordUrl live in LessonPlanFamilyResource::table() and
     * are applied normally through configureTable() — no makeTable() override needed.
     */
    protected function getTableQuery(): Builder
    {
        return LessonPlanVersion::query()
            ->with(['family.subjectGrade.subject', 'contributor']);
    }
}
