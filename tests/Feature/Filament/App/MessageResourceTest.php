<?php

use App\Filament\App\Resources\MessageResource;
use App\Filament\App\Resources\MessageResource\Pages\ComposeMessage;
use App\Filament\App\Resources\MessageResource\Pages\ListMessages;
use App\Filament\App\Resources\MessageResource\Pages\ViewMessage;
use App\Models\Message;
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
