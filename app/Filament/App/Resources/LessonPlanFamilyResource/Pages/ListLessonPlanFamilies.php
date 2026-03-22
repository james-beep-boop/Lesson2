<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLessonPlanFamilies extends ListRecords
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($user->isSiteAdmin() || \App\Models\SubjectGrade::where('subject_admin_user_id', $user->id)->exists()) {
            return [CreateAction::make()->label('Add Lesson Plan')];
        }

        return [];
    }
}
