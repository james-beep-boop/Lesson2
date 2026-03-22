<?php

use App\Models\Favorite;
use App\Services\FavoriteService;

test('favoriting a version records user_id, family_id, version_id', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $user = makeTeacher();

    $service = new FavoriteService;
    $service->upsert($user, $version);

    $favorite = Favorite::where('user_id', $user->id)
        ->where('lesson_plan_family_id', $family->id)
        ->first();

    expect($favorite)->not->toBeNull();
    expect($favorite->lesson_plan_version_id)->toBe($version->id);
});

test('favoriting a second version of the same family replaces the first (upsert)', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $user = makeTeacher();
    $service = new FavoriteService;

    $v2 = \App\Models\LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.0.1',
    ]);

    $service->upsert($user, $v1);
    expect(Favorite::where('user_id', $user->id)->count())->toBe(1);

    $service->upsert($user, $v2);
    expect(Favorite::where('user_id', $user->id)->count())->toBe(1);

    expect(Favorite::where('user_id', $user->id)->first()->lesson_plan_version_id)->toBe($v2->id);
});

test('a user cannot hold two favorites for the same family', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $user = makeTeacher();
    $service = new FavoriteService;

    $v2 = \App\Models\LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.1.0',
    ]);

    $service->upsert($user, $v1);
    $service->upsert($user, $v2);

    $count = Favorite::where('user_id', $user->id)
        ->where('lesson_plan_family_id', $family->id)
        ->count();

    expect($count)->toBe(1);
});

test('favorites are user-specific', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $user1 = makeTeacher();
    $user2 = makeTeacher();
    $service = new FavoriteService;

    $service->upsert($user1, $version);
    $service->upsert($user2, $version);

    expect(Favorite::where('user_id', $user1->id)->count())->toBe(1);
    expect(Favorite::where('user_id', $user2->id)->count())->toBe(1);
    expect(Favorite::count())->toBe(2);
});
