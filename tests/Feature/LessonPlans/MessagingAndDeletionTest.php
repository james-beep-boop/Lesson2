<?php

use App\Models\DeletionRequest;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Services\DeletionRequestService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
});

test('deletion request creates messages to contributor and site admins', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();

    $contributor = $version->contributor;

    $service = new DeletionRequestService;
    $service->request($version, $subjectAdmin, 'Outdated content');

    // Contributor should receive a message
    $messageToContributor = Message::where('to_user_id', $contributor->id)
        ->where('from_user_id', $subjectAdmin->id)
        ->first();

    // Site Admin should receive a message
    $messageToAdmin = Message::where('to_user_id', $siteAdmin->id)
        ->where('from_user_id', $subjectAdmin->id)
        ->first();

    expect($messageToContributor)->not->toBeNull();
    expect($messageToAdmin)->not->toBeNull();
});

test('deletion request record is created', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $service = new DeletionRequestService;

    $request = $service->request($version, $subjectAdmin, 'Test reason');

    expect(DeletionRequest::find($request->id))->not->toBeNull();
    expect($request->lesson_plan_version_id)->toBe($version->id);
});

test('resolve marks request resolved and hard-deletes version', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    $service = new DeletionRequestService;

    $request = $service->request($version, $subjectAdmin);
    $versionId = $version->id;

    $service->resolve($request, $siteAdmin);

    expect(DeletionRequest::find($request->id)->resolved_at)->not->toBeNull();
    expect(LessonPlanVersion::find($versionId))->toBeNull();
});

test('resolve clears official_version_id if deleted version was official', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $family->official_version_id = $version->id;
    $family->save();

    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    $service = new DeletionRequestService;

    $request = $service->request($version, $subjectAdmin);
    $service->resolve($request, $siteAdmin);

    expect(LessonPlanFamily::find($family->id)->official_version_id)->toBeNull();
});

test('resolve does not clear official_version_id if a different version is official', function () {
    $sg = makeSubjectGrade();
    [$family, $v1] = makeFamilyWithVersion($sg);
    $v2 = LessonPlanVersion::factory()->create([
        'lesson_plan_family_id' => $family->id,
        'version' => '1.1.0',
    ]);
    $family->official_version_id = $v2->id;
    $family->save();

    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    $service = new DeletionRequestService;

    $request = $service->request($v1, $subjectAdmin);
    $service->resolve($request, $siteAdmin);

    expect(LessonPlanFamily::find($family->id)->official_version_id)->toBe($v2->id);
});

test('messages have null read_at when first created', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    $service = new DeletionRequestService;

    $service->request($version, $subjectAdmin);

    $unread = Message::where('to_user_id', $siteAdmin->id)->whereNull('read_at')->count();
    expect($unread)->toBeGreaterThan(0);
});

test('marking a message read sets read_at', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();
    $service = new DeletionRequestService;

    $service->request($version, $subjectAdmin);

    $message = Message::where('to_user_id', $siteAdmin->id)->first();
    expect($message->read_at)->toBeNull();

    $message->read_at = now();
    $message->save();

    expect($message->fresh()->read_at)->not->toBeNull();
});
