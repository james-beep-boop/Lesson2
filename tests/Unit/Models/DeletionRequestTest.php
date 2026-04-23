<?php

use App\Models\DeletionRequest;

test('deletion request scopes filter pending and resolved records', function () {
    $sg = makeSubjectGrade();
    [, $version] = makeFamilyWithVersion($sg);
    $requester = makeTeacher();
    $resolver = makeTeacher();

    $pending = new DeletionRequest([
        'lesson_plan_version_id' => $version->id,
        'reason' => 'Pending request',
    ]);
    $pending->requested_by_user_id = $requester->id;
    $pending->save();

    $resolved = new DeletionRequest([
        'lesson_plan_version_id' => $version->id,
        'reason' => 'Resolved request',
    ]);
    $resolved->requested_by_user_id = $requester->id;
    $resolved->resolved_by_user_id = $resolver->id;
    $resolved->resolved_at = now();
    $resolved->save();

    expect(DeletionRequest::pending()->pluck('id')->all())->toBe([$pending->id]);
    expect(DeletionRequest::resolved()->pluck('id')->all())->toBe([$resolved->id]);
});
