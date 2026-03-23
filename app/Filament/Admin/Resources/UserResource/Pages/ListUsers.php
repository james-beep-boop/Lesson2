<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add User'),
        ];
    }

    public function getTabs(): array
    {
        // Fetch once — used in both badge labels and modifyQueryUsing closures.
        // Exclude system user in all counts (base table query already does this).
        $subjectAdminIds = SubjectGrade::whereNotNull('subject_admin_user_id')
            ->pluck('subject_admin_user_id');

        $all           = User::where('is_system', false)->count();
        $siteAdmins    = User::where('is_system', false)->role('site_administrator')->count();
        $subjectAdmins = User::where('is_system', false)->whereIn('id', $subjectAdminIds)->count();
        $editors       = User::where('is_system', false)
            ->whereHas('subjectGrades', fn (Builder $q) => $q->wherePivot('role', 'editor'))
            ->count();
        $teachers      = User::where('is_system', false)
            ->whereDoesntHave('roles')
            ->whereNotIn('id', $subjectAdminIds)
            ->whereDoesntHave('subjectGrades', fn (Builder $q) => $q->wherePivot('role', 'editor'))
            ->count();

        return [
            'all' => Tab::make("All ({$all})"),

            'site_admins' => Tab::make("Site Admins ({$siteAdmins})")
                ->modifyQueryUsing(fn (Builder $query) => $query->role('site_administrator')),

            'subject_admins' => Tab::make("Subject Admins ({$subjectAdmins})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('id', $subjectAdminIds)),

            'editors' => Tab::make("Editors ({$editors})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'subjectGrades',
                    fn (Builder $q) => $q->wherePivot('role', 'editor')
                )),

            'teachers' => Tab::make("Teachers ({$teachers})")
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereDoesntHave('roles')
                    ->whereNotIn('id', $subjectAdminIds)
                    ->whereDoesntHave('subjectGrades', fn (Builder $q) => $q->wherePivot('role', 'editor'))
                ),
        ];
    }
}
