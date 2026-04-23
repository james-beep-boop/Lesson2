<?php

namespace App\Filament\Admin\Resources\SubjectGradeResource\Pages;

use App\Filament\Admin\Resources\SubjectGradeResource;
use App\Models\SubjectGrade;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubjectGrades extends ListRecords
{
    protected static string $resource = SubjectGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        $hasAdmin = SubjectGrade::whereNotNull('subject_admin_user_id')->count();
        $noAdmin = SubjectGrade::whereNull('subject_admin_user_id')->count();

        return [
            'all' => Tab::make('All'),

            'has_admin' => Tab::make("Has Subject Admin ({$hasAdmin})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('subject_admin_user_id')),

            'no_admin' => Tab::make("No Subject Admin ({$noAdmin})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('subject_admin_user_id')),
        ];
    }
}
