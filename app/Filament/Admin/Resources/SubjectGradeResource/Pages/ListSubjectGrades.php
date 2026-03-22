<?php

namespace App\Filament\Admin\Resources\SubjectGradeResource\Pages;

use App\Filament\Admin\Resources\SubjectGradeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListSubjectGrades extends ListRecords
{
    protected static string $resource = SubjectGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
