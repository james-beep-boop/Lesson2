<?php

namespace App\Filament\App\Resources\MessageResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Filament\App\Resources\MessageResource;
use App\Models\DeletionRequest;
use App\Models\LessonPlanVersion;
use App\Services\DeletionRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    public ?int $deletionRequestId = null;

    /**
     * Custom blade view — bypasses default infolist rendering while still
     * using ViewRecord for correct route binding and canView() authorization.
     * ViewRecord also scopes the lookup through getEloquentQuery() so a user
     * cannot access another user's message by guessing its ID.
     */
    protected string $view = 'filament.app.pages.view-message';

    public function mount(int|string $record): void
    {
        // Resolves record through the scoped query and runs canView() check.
        parent::mount($record);

        // Mark as read the first time the message is opened.
        if (! $this->record->isRead()) {
            $this->record->read_at = now();
            $this->record->save();
            $this->record->refresh();
        }

        $this->deletionRequestId = $this->resolveDeletionRequest()?->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(MessageResource::getUrl('compose', [
                    'to' => $this->record->from_user_id,
                    'subject' => 'Re: '.$this->record->subject,
                ])),
            Action::make('viewThisPlan')
                ->label('View This Plan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->visible(fn (): bool => $this->deletionRequestVersion() !== null && auth()->user()?->isSiteAdmin())
                ->url(function (): string {
                    $version = $this->deletionRequestVersion();

                    return $version
                        ? LessonPlanFamilyResource::viewUrl($version->family, $version)
                        : MessageResource::getUrl('index');
                }),
            Action::make('deleteThisPlan')
                ->label('Delete This Plan')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->deletionRequestVersion() !== null && auth()->user()?->isSiteAdmin())
                ->action(function (): void {
                    $deletionRequest = $this->resolveDeletionRequest();

                    if (! $deletionRequest || ! $deletionRequest->version) {
                        Notification::make()
                            ->title('This deletion request is no longer actionable.')
                            ->warning()
                            ->send();

                        return;
                    }

                    app(DeletionRequestService::class)->resolve($deletionRequest, auth()->user());

                    Notification::make()
                        ->title('Lesson plan deleted.')
                        ->success()
                        ->send();

                    $this->redirect(MessageResource::getUrl('index'));
                }),
            Action::make('back')
                ->label('Back to Inbox')
                ->icon('heroicon-o-arrow-left')
                ->url(MessageResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->subject;
    }

    private function deletionRequestVersion(): ?LessonPlanVersion
    {
        return $this->resolveDeletionRequest()?->version;
    }

    private function resolveDeletionRequest(): ?DeletionRequest
    {
        if ($this->deletionRequestId) {
            return DeletionRequest::query()
                ->with('version.family.subjectGrade.subject')
                ->whereKey($this->deletionRequestId)
                ->whereNull('resolved_at')
                ->first();
        }

        if (preg_match('/\[deletion_request:(\d+)\]\s*$/', $this->record->body, $matches)) {
            return DeletionRequest::query()
                ->with('version.family.subjectGrade.subject')
                ->whereKey((int) $matches[1])
                ->whereNull('resolved_at')
                ->first();
        }

        if (! preg_match('/Lesson plan ID:\s*(\d+)/i', $this->record->body, $familyMatches)) {
            if (! preg_match('/lesson plan ID\s+(\d+)/i', $this->record->body, $familyMatches)) {
                return null;
            }
        }

        if (! preg_match('/Deletion request:\s+version\s+([0-9]+\.[0-9]+\.[0-9]+)/i', $this->record->subject, $versionMatches)) {
            return null;
        }

        return DeletionRequest::query()
            ->with('version.family.subjectGrade.subject')
            ->whereNull('resolved_at')
            ->whereHas('version', function ($query) use ($familyMatches, $versionMatches) {
                $query
                    ->where('lesson_plan_family_id', (int) $familyMatches[1])
                    ->where('version', $versionMatches[1]);
            })
            ->first();
    }
}
