<?php

use App\Filament\App\Resources\MessageResource;
use App\Filament\App\Resources\MessageResource\Pages\ComposeMessage;
use App\Filament\App\Resources\MessageResource\Pages\ListMessages;
use App\Filament\App\Resources\MessageResource\Pages\ViewMessage;
use App\Models\DeletionRequest;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use App\Services\DeletionRequestService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('inbox loads for authenticated user', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(ListMessages::class)
        ->assertOk();
});

test('inbox only shows messages addressed to the current user', function () {
    $recipient = makeTeacher();
    $sender = makeTeacher();
    $otherUser = makeTeacher();

    $inboxMessage = new Message(['to_user_id' => $recipient->id, 'subject' => 'For you', 'body' => 'Hello']);
    $inboxMessage->from_user_id = $sender->id;
    $inboxMessage->save();

    $notMyMessage = new Message(['to_user_id' => $otherUser->id, 'subject' => 'Not for you', 'body' => 'Hi']);
    $notMyMessage->from_user_id = $sender->id;
    $notMyMessage->save();

    $this->actingAs($recipient);

    Livewire::test(ListMessages::class)
        ->assertCanSeeTableRecords([$inboxMessage])
        ->assertCanNotSeeTableRecords([$notMyMessage]);
});

test('compose message creates a message and redirects to inbox', function () {
    $sender = makeTeacher();
    $recipient = makeTeacher();

    $this->actingAs($sender);

    Livewire::test(ComposeMessage::class)
        ->fillForm([
            'to_user_id' => $recipient->id,
            'subject' => 'Hello there',
            'body' => 'Test message body.',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    expect(
        Message::where('to_user_id', $recipient->id)
            ->where('from_user_id', $sender->id)
            ->where('subject', 'Hello there')
            ->exists()
    )->toBeTrue();
});

test('compose message requires all fields', function () {
    $this->actingAs(makeTeacher());

    Livewire::test(ComposeMessage::class)
        ->fillForm(['to_user_id' => null, 'subject' => null, 'body' => null])
        ->call('create')
        ->assertHasFormErrors(['to_user_id' => 'required', 'subject' => 'required', 'body' => 'required']);
});

test('compose message does not list unverified users as recipients', function () {
    $sender = makeTeacher();
    $verifiedRecipient = makeTeacher();
    $unverifiedRecipient = User::factory()->unverified()->create();

    $this->actingAs($sender);

    Livewire::test(ComposeMessage::class)
        ->assertSee($verifiedRecipient->name)
        ->assertDontSee($unverifiedRecipient->name);
});

test('compose message rejects unverified recipients', function () {
    $sender = makeTeacher();
    $unverifiedRecipient = User::factory()->unverified()->create();

    $this->actingAs($sender);

    Livewire::test(ComposeMessage::class)
        ->fillForm([
            'to_user_id' => $unverifiedRecipient->id,
            'subject' => 'Hello there',
            'body' => 'Test message body.',
        ])
        ->call('create')
        ->assertHasFormErrors(['to_user_id']);
});

test('viewing a message marks it as read', function () {
    $recipient = makeTeacher();
    $sender = makeTeacher();

    $message = new Message(['to_user_id' => $recipient->id, 'subject' => 'Unread', 'body' => 'Check me']);
    $message->from_user_id = $sender->id;
    $message->save();

    expect($message->read_at)->toBeNull();

    $this->actingAs($recipient);

    Livewire::test(ViewMessage::class, ['record' => $message->id])
        ->assertOk();

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('a user cannot view another users message', function () {
    $owner = makeTeacher();
    $intruder = makeTeacher();
    $sender = makeTeacher();

    $message = new Message(['to_user_id' => $owner->id, 'subject' => 'Private', 'body' => 'Secret']);
    $message->from_user_id = $sender->id;
    $message->save();

    // The query is scoped to to_user_id = auth()->id(), so the record is simply
    // not found for the intruder (404), not a 403 — existence is not revealed.
    $this->actingAs($intruder)
        ->get(MessageResource::getUrl('view', ['record' => $message->id]))
        ->assertNotFound();
});

test('site admin can resolve a pending deletion request from a new-format inbox message', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();

    $this->actingAs($subjectAdmin);
    $request = app(DeletionRequestService::class)->request($version, $subjectAdmin, 'Superseded');
    $message = Message::where('to_user_id', $siteAdmin->id)
        ->where('subject', 'Deletion request: version '.$version->version)
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($siteAdmin);

    Livewire::test(ViewMessage::class, ['record' => $message->id])
        ->assertActionVisible('viewThisPlan')
        ->assertActionVisible('deleteThisPlan')
        ->callAction('deleteThisPlan')
        ->assertRedirect(MessageResource::getUrl('index'));

    expect(DeletionRequest::find($request->id)?->resolved_at)->not->toBeNull();
    expect(LessonPlanVersion::find($version->id))->toBeNull();
});

test('structured deletion marker is not shown in the rendered message body', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();

    $this->actingAs($subjectAdmin);
    app(DeletionRequestService::class)->request($version, $subjectAdmin, 'Superseded');
    $message = Message::where('to_user_id', $siteAdmin->id)
        ->where('subject', 'Deletion request: version '.$version->version)
        ->latest('id')
        ->firstOrFail();

    $response = $this->actingAs($siteAdmin)
        ->get(MessageResource::getUrl('view', ['record' => $message->id]))
        ->assertOk()
        ->assertSee('Reason: Superseded');

    preg_match('/<div[^>]*data-testid="message-body"[^>]*>(?P<body>.*?)<\/div>/s', $response->getContent(), $matches);
    $bodyText = html_entity_decode(strip_tags($matches['body'] ?? ''));

    expect($matches)->toHaveKey('body');
    expect($bodyText)->not->toContain('[deletion_request:');
});

test('deletion request parser ignores marker-like text in the reason and uses the trailing marker', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();

    $this->actingAs($subjectAdmin);
    $request = app(DeletionRequestService::class)->request(
        $version,
        $subjectAdmin,
        'Please review [deletion_request:999] before deleting'
    );
    $message = Message::where('to_user_id', $siteAdmin->id)
        ->where('subject', 'Deletion request: version '.$version->version)
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($siteAdmin);

    Livewire::test(ViewMessage::class, ['record' => $message->id])
        ->assertActionVisible('deleteThisPlan')
        ->callAction('deleteThisPlan')
        ->assertRedirect(MessageResource::getUrl('index'));

    expect(DeletionRequest::find($request->id)?->resolved_at)->not->toBeNull();
});

test('site admin can resolve a pending deletion request from a legacy inbox message body', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $subjectAdmin = makeSubjectAdmin($sg);
    $siteAdmin = makeSiteAdmin();

    $request = new DeletionRequest([
        'lesson_plan_version_id' => $version->id,
        'reason' => 'Legacy pending request',
    ]);
    $request->requested_by_user_id = $subjectAdmin->id;
    $request->save();

    $legacyMessage = new Message([
        'to_user_id' => $siteAdmin->id,
        'subject' => 'Deletion request: version '.$version->version,
        'body' => $subjectAdmin->username.' has requested deletion of version '
            .$version->version.' of lesson plan ID '.$version->lesson_plan_family_id.".\n\n"
            .'Reason: Legacy pending request',
    ]);
    $legacyMessage->from_user_id = $subjectAdmin->id;
    $legacyMessage->save();

    $this->actingAs($siteAdmin);

    Livewire::test(ViewMessage::class, ['record' => $legacyMessage->id])
        ->assertActionVisible('viewThisPlan')
        ->assertActionVisible('deleteThisPlan');
});
