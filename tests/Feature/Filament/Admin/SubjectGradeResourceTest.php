<?php

use App\Filament\Admin\Resources\SubjectGradeResource\Pages\ListSubjectGrades;
use App\Models\Subject;
use App\Models\SubjectGrade;
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
