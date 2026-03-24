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
        // Avoid Spatie's role() scope which throws RoleDoesNotExist when the role
        // hasn't been seeded yet. Use whereHas instead — safe on any environment.
        $subjectAdminIds = SubjectGrade::whereNotNull('subject_admin_user_id')
            ->pluck('subject_admin_user_id');

        $isSiteAdmin = fn (Builder $q) => $q->whereHas(
            'roles',
            fn (Builder $r) => $r->where('name', 'site_administrator')
        );

        // wherePivot() is only valid on a BelongsToMany instance, not inside
        // whereHas/whereDoesntHave callbacks — it generates `"pivot" = ?`
        // which is an unknown column on MariaDB and causes a 500. Use the
        // explicit pivot table name instead.
        $isEditor = fn (Builder $q) => $q->whereHas(
            'subjectGrades',
            fn (Builder $r) => $r->where('subject_grade_user.role', 'editor')
        );

        $all           = User::where('is_system', false)->count();
        $siteAdmins    = User::where('is_system', false)->tap($isSiteAdmin)->count();
        $subjectAdmins = User::where('is_system', false)->whereIn('id', $subjectAdminIds)->count();
        $editors       = User::where('is_system', false)->tap($isEditor)->count();
        $teachers      = User::where('is_system', false)
            ->whereDoesntHave('roles')
            ->whereNotIn('id', $subjectAdminIds)
            ->whereDoesntHave('subjectGrades', fn (Builder $q) => $q->where('subject_grade_user.role', 'editor'))
            ->count();

        return [
            'all' => Tab::make("All ({$all})"),

            'site_admins' => Tab::make("Site Admins ({$siteAdmins})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $r) => $r->where('name', 'site_administrator')
                )),

            'subject_admins' => Tab::make("Subject Admins ({$subjectAdmins})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('id', $subjectAdminIds)),

            'editors' => Tab::make("Editors ({$editors})")
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'subjectGrades',
                    fn (Builder $r) => $r->where('subject_grade_user.role', 'editor')
                )),

            'teachers' => Tab::make("Teachers ({$teachers})")
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereDoesntHave('roles')
                    ->whereNotIn('id', $subjectAdminIds)
                    ->whereDoesntHave('subjectGrades', fn (Builder $q) => $q->where('subject_grade_user.role', 'editor'))
                ),
        ];
    }
}
