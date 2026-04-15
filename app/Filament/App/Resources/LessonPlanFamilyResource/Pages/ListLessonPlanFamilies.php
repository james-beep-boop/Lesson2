<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Concerns\HasLessonPlanVersionTabs;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanVersion;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLessonPlanFamilies extends ListRecords
{
    use HasLessonPlanVersionTabs;

    protected static string $resource = LessonPlanFamilyResource::class;

    // Defaults to null so Filament's loadDefaultActiveTab() picks the first tab key ('all').
    public ?string $activeTab = null;

    protected function getHeaderActions(): array
    {
        // Creation was moved to the admin dashboard; no create button on the list page.
        return [];
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
