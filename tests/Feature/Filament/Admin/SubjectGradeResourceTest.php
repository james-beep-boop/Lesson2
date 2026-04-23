<?php

use App\Filament\Admin\Resources\SubjectGradeResource\Pages\ListSubjectGrades;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('subject grades can be filtered by grade', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $grade10 = SubjectGrade::factory()->create([
        'subject_id' => $subject->id,
        'grade' => 10,
    ]);
    $grade8 = SubjectGrade::factory()->create([
        'subject_id' => $subject->id,
        'grade' => 8,
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(ListSubjectGrades::class)
        ->assertCanSeeTableRecords([$grade10, $grade8])
        ->filterTable('grade', 10)
        ->assertCanSeeTableRecords([$grade10])
        ->assertCanNotSeeTableRecords([$grade8]);
});

test('subject grades can be filtered by subject admin assignment tabs', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $subjectAdmin = User::factory()->create();
    $withAdmin = SubjectGrade::factory()->create([
        'subject_id' => $subject->id,
        'grade' => 10,
        'subject_admin_user_id' => $subjectAdmin->id,
    ]);
    $withoutAdmin = SubjectGrade::factory()->create([
        'subject_id' => $subject->id,
        'grade' => 8,
        'subject_admin_user_id' => null,
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(ListSubjectGrades::class)
        ->set('activeTab', 'no_admin')
        ->assertCanSeeTableRecords([$withoutAdmin])
        ->assertCanNotSeeTableRecords([$withAdmin]);

    Livewire::test(ListSubjectGrades::class)
        ->set('activeTab', 'has_admin')
        ->assertCanSeeTableRecords([$withAdmin])
        ->assertCanNotSeeTableRecords([$withoutAdmin])
        ->set('activeTab', 'no_admin')
        ->assertCanSeeTableRecords([$withoutAdmin])
        ->assertCanNotSeeTableRecords([$withAdmin]);
});

test('subject admin can be removed from a subject grade', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $subjectAdmin = User::factory()->create();
    $subjectGrade = SubjectGrade::factory()->create([
        'subject_id' => $subject->id,
        'grade' => 10,
        'subject_admin_user_id' => $subjectAdmin->id,
    ]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(ListSubjectGrades::class)
        ->callTableAction('removeSubjectAdmin', $subjectGrade)
        ->assertNotified();

    expect($subjectGrade->fresh()->subject_admin_user_id)->toBeNull();
});
