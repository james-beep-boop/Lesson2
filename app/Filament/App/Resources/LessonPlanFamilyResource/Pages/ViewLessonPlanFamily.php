<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Ai\Agents\LessonPlanAdvisor;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Mail\LessonPlanPdfMail;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use App\Services\DeletionRequestService;
use App\Services\DiffService;
use App\Services\FavoriteService;
use App\Services\LessonPlanPdfService;
use App\Services\MarkdownSelectionMatcher;
use App\Services\TranslationService;
use App\Services\VersionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Streaming\Events\TextDelta;

class ViewLessonPlanFamily extends Page
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected string $view = 'filament.app.pages.view-lesson-plan-family';

    public LessonPlanFamily $record;

    public ?LessonPlanVersion $selectedVersion = null;

    public ?LessonPlanVersion $compareVersion = null;

    public bool $compareMode = false;

    public string $diffLayout = 'side-by-side';

    public string $diffHtml = '';

    public string $diffCss = '';

    public bool $editMode = false;

    public string $editContent = '';

    public string $versionBump = 'patch';

    public ?string $revisionNote = null;

    public ?Favorite $userFavorite = null;

    public bool $hasPendingDeletion = false;

    public bool $showDeletionForm = false;

    public string $deletionReason = '';

    // AI panel state
    public bool $aiPanelOpen = false;

    public string $aiPrompt = '';

    public string $aiResponse = '';

    // Translation preview state
    public bool $translationPanelOpen = false;

    public string $translatedContent = '';

    public bool $showTranslationEmailPanel = false;

    public string $translationEmailTo = '';

    public string $translationEmailMessage = '';

    // -------------------------------------------------------------------------
    // Lesson-context messaging state
    // -------------------------------------------------------------------------

    public bool $showMessageModal = false;

    /** author | subject_admin | site_admin | any_user */
    public string $messageRecipientType = 'author';

    public ?int $messageToUserId = null;

    public string $messageSubject = '';

    public string $messageBody = '';

    public string $userSearchQuery = '';

    // -------------------------------------------------------------------------
    // Email PDF state
    // -------------------------------------------------------------------------

    public bool $showEmailPdfModal = false;

    public string $emailPdfTo = '';

    public string $emailPdfMessage = '';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function getTitle(): string
    {
        return 'View / Edit Lesson Plan';
    }

    public function mount(LessonPlanFamily $record): void
    {
        $this->record = $record->load(['versions.contributor', 'officialVersion', 'latestVersion', 'subjectGrade.subject', 'subjectGrade.subjectAdmin']);
        $this->selectedVersion = $record->officialVersion ?? $record->latestVersion;
        $this->syncDerivedState();
    }

    private function syncDerivedState(): void
    {
        $user = auth()->user();

        $this->userFavorite = $user
            ? Favorite::where('user_id', $user->id)
                ->where('lesson_plan_family_id', $this->record->id)
                ->with('version')
                ->first()
            : null;

        $this->hasPendingDeletion = (bool) ($this->selectedVersion?->deletionRequests()
            ->whereNull('resolved_at')
            ->exists());
    }

    // -------------------------------------------------------------------------
    // Version selection
    // -------------------------------------------------------------------------

    public function selectVersion(int $versionId): void
    {
        $version = $this->record->versions->find($versionId);

        if (! $version) {
            return;
        }

        $this->selectedVersion = $version;
        $this->compareMode = false;
        $this->compareVersion = null;
        $this->diffHtml = '';
        $this->hasPendingDeletion = (bool) $this->selectedVersion->deletionRequests()
            ->whereNull('resolved_at')
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function enterEditMode(): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);
        $this->enterEditModeIfNeeded();
    }

    private function enterEditModeIfNeeded(): void
    {
        if (! $this->editMode) {
            $this->editContent = $this->selectedVersion?->content ?? '';
            $this->editMode = true;
        }
    }

    public function saveNewVersion(VersionService $versionService): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);

        $this->validate([
            'editContent' => 'required|string',
            'versionBump' => 'required|in:patch,minor,major',
        ]);

        $version = $versionService->addVersion(
            $this->record,
            $this->editContent,
            $this->versionBump,
            $this->revisionNote ?: null,
            auth()->user()
        );

        $this->record->refresh();
        $this->selectedVersion = $version;
        $this->editMode = false;
        $this->revisionNote = null;
        $this->hasPendingDeletion = false;

        Notification::make('version-saved')->title('New version saved.')->success()->send();
    }

    public function markOfficial(VersionService $versionService): void
    {
        $this->authorize('markOfficial', $this->selectedVersion);

        $versionService->setOfficialVersion($this->record, $this->selectedVersion);
        $this->record->refresh();
        $this->selectedVersion = $this->selectedVersion->fresh();

        Notification::make('official-updated')->title('Official version updated.')->success()->send();
    }

    public function favorite(FavoriteService $favoriteService): void
    {
        abort_unless(auth()->check(), 403);

        $this->userFavorite = $favoriteService->upsert(auth()->user(), $this->selectedVersion);

        Notification::make('favorited')->title('Added to favorites.')->success()->send();
    }

    /** Returns ['major' => '2.0.0', 'minor' => '1.1.0', 'patch' => '1.0.1'] based on current versions. */
    public function versionPreviews(): array
    {
        return app(VersionService::class)->computeAllNextVersions($this->record);
    }

    public function requestDeletion(DeletionRequestService $service): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('requestDeletion', $this->selectedVersion);

        $user = auth()->user();

        if ($this->hasPendingDeletion) {
            Notification::make('deletion-already-pending')
                ->title('A pending deletion request already exists for this version.')
                ->warning()
                ->send();
            $this->showDeletionForm = false;

            return;
        }

        $service->request(
            $this->selectedVersion,
            $user,
            filled($this->deletionReason) ? $this->deletionReason : null
        );

        $this->showDeletionForm = false;
        $this->deletionReason = '';
        $this->hasPendingDeletion = true;

        Notification::make('deletion-requested')
            ->title('Deletion request submitted — Site Admins have been notified.')
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Compare / visual diff
    // -------------------------------------------------------------------------

    public function enterCompareMode(int $versionId): void
    {
        $other = $this->record->versions->find($versionId);

        if (! $other) {
            return;
        }

        $this->compareVersion = $other;
        $this->compareMode = true;
        $this->computeDiff();
    }

    public function compareToPreviousVersion(VersionService $versionService): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $currentVersion = $this->selectedVersion->version;

        $previous = $this->record->versions
            ->filter(fn (LessonPlanVersion $v) => $v->id !== $this->selectedVersion->id
                && $versionService->compareVersions($v->version, $currentVersion) < 0
            )
            ->sortByDesc(fn (LessonPlanVersion $v) => $v->version)
            ->first();

        if (! $previous) {
            Notification::make('no-previous-version')
                ->title('No previous version exists for this lesson.')
                ->warning()
                ->send();

            return;
        }

        $this->compareVersion = $previous;
        $this->compareMode = true;
        $this->computeDiff();
    }

    public function compareToOfficialVersion(): void
    {
        if (! $this->selectedVersion || ! $this->record->officialVersion) {
            Notification::make('no-official-version')
                ->title('No official version is set for this lesson.')
                ->warning()
                ->send();

            return;
        }

        if ($this->record->officialVersion->id === $this->selectedVersion->id) {
            Notification::make('already-official')
                ->title('The selected version is already the official version.')
                ->info()
                ->send();

            return;
        }

        $this->compareVersion = $this->record->officialVersion;
        $this->compareMode = true;
        $this->computeDiff();
    }

    public function toggleDiffLayout(): void
    {
        $this->diffLayout = $this->diffLayout === 'side-by-side' ? 'stacked' : 'side-by-side';
        $this->computeDiff();
    }

    private function computeDiff(): void
    {
        if (! $this->selectedVersion || ! $this->compareVersion) {
            return;
        }

        $diffService = app(DiffService::class);

        $result = $this->diffLayout === 'side-by-side'
            ? $diffService->sideBySide($this->compareVersion->content ?? '', $this->selectedVersion->content ?? '')
            : $diffService->unified($this->compareVersion->content ?? '', $this->selectedVersion->content ?? '');

        $this->diffHtml = $result['html'];
        $this->diffCss = $result['css'];
    }

    // -------------------------------------------------------------------------
    // Text-selection mapping (for inline edit)
    // -------------------------------------------------------------------------

    /**
     * @return array{start: int, end: int, confident: bool}
     */
    public function mapSelectionToSource(
        string $text,
        string $before,
        string $after,
        MarkdownSelectionMatcher $matcher,
    ): array {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);
        $this->enterEditModeIfNeeded();

        $result = $matcher->find($this->editContent, $text, $before, $after);

        return [
            'start' => $result->start,
            'end' => $result->end,
            'confident' => $result->confident,
        ];
    }

    // -------------------------------------------------------------------------
    // Ask AI
    // -------------------------------------------------------------------------

    public function openAiPanel(): void
    {
        $this->authorize('askAi', $this->selectedVersion);
        $this->aiPanelOpen = true;
        $this->dispatch('scroll-to-ai-panel');
    }

    // -------------------------------------------------------------------------
    // Swahili translation preview
    // -------------------------------------------------------------------------

    public function openTranslationPanel(): void
    {
        $this->authorize('translate', $this->selectedVersion);

        $alreadyOpen = $this->translationPanelOpen;

        $this->translatedContent = '';
        $this->translationPanelOpen = true;

        if (! $alreadyOpen) {
            Notification::make('translation-started')
                ->title('Translation in progress')
                ->body('The Swahili translation will appear below the English content — scroll down to see it.')
                ->info()
                ->send();
        }
    }

    public function translatePreview(TranslationService $translationService): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('translate', $this->selectedVersion);

        set_time_limit(120);

        try {
            $accumulated = '';

            foreach ($translationService->streamTranslation($this->selectedVersion->content) as $event) {
                if ($event instanceof TextDelta) {
                    $accumulated .= $event->delta;
                    $this->stream($event->delta, false, 'translatedContent');
                }
            }

            $this->translatedContent = $accumulated;
        } catch (\Throwable) {
            $this->translationPanelOpen = false;

            Notification::make('translation-failed')
                ->title('Translation unavailable')
                ->body('The translation service could not complete the request. Please ensure AI suggestions are configured.')
                ->danger()
                ->send();
        }
    }

    public function openTranslationEmailPanel(): void
    {
        abort_unless(auth()->check(), 403);
        $this->showTranslationEmailPanel = true;
        $this->translationEmailTo = '';
        $this->translationEmailMessage = '';
    }

    public function sendTranslationEmailPdf(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'translationEmailTo' => 'required|email|max:255',
        ]);

        if (! $this->selectedVersion || ! $this->translatedContent) {
            return;
        }

        $version = $this->selectedVersion;
        $version->load(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        try {
            $pdfContent = app(LessonPlanPdfService::class)->renderTranslation(
                $version->family,
                $version,
                $this->translatedContent,
            );

            Mail::to($this->translationEmailTo)->send(new LessonPlanPdfMail(
                version: $version,
                pdfContent: $pdfContent,
                senderName: auth()->user()->name,
                customMessage: 'Swahili translation — preview only, not saved to database.'
                    .($this->translationEmailMessage ? "\n\n".$this->translationEmailMessage : ''),
            ));

            $this->showTranslationEmailPanel = false;
            $this->translationEmailTo = '';
            $this->translationEmailMessage = '';

            Notification::make('translation-email-sent')
                ->title('Translation PDF sent successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make('translation-email-error')
                ->title('Failed to send translation PDF.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function submitAiPrompt(): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('askAi', $this->selectedVersion);

        if (blank($this->aiPrompt)) {
            return;
        }

        $content = $this->selectedVersion?->content ?? '';
        $prompt = "The following is the current lesson plan content:\n\n{$content}"
                 ."\n\n---\n\nUser's request: {$this->aiPrompt}";

        $this->aiResponse = '';
        $accumulated = '';

        set_time_limit(120);

        $stream = LessonPlanAdvisor::make()->stream($prompt);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $accumulated .= $event->delta;
                $this->stream($event->delta, false, 'aiResponse');
            }
        }

        $this->aiResponse = $accumulated;
    }

    // -------------------------------------------------------------------------
    // Lesson-context messaging
    // -------------------------------------------------------------------------

    /**
     * Open the message modal, pre-filling subject/body for the given recipient type.
     * Allowed types: author | subject_admin | site_admin | any_user
     */
    public function openMessageModal(string $recipientType): void
    {
        abort_unless(auth()->check() && ! auth()->user()->is_system, 403);

        $allowed = ['author', 'subject_admin', 'site_admin', 'any_user'];
        if (! in_array($recipientType, $allowed)) {
            return;
        }

        $this->messageRecipientType = $recipientType;
        $this->showMessageModal = true;
        $this->messageToUserId = null;
        $this->userSearchQuery = '';
        $this->messageSubject = $this->buildMessageSubject();
        $this->messageBody = $this->buildMessageBody();
    }

    public function selectMessageUser(int $userId): void
    {
        $user = User::where('id', $userId)->where('is_system', false)->first();
        if ($user) {
            $this->messageToUserId = $user->id;
            $this->userSearchQuery = '';
        }
    }

    public function getMessageUserSearchResults(): Collection
    {
        if (strlen($this->userSearchQuery) < 1) {
            return collect();
        }

        return User::where('is_system', false)
            ->where('id', '!=', auth()->id())
            ->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->userSearchQuery.'%')
                ->orWhere('email', 'like', '%'.$this->userSearchQuery.'%')
            )
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function sendLessonMessage(): void
    {
        abort_unless(auth()->check() && ! auth()->user()->is_system, 403);

        $this->validate([
            'messageSubject' => 'required|string|max:255',
            'messageBody' => 'required|string',
        ]);

        $sender = auth()->user();
        $recipients = $this->resolveMessageRecipients();

        if (empty($recipients)) {
            Notification::make('message-no-recipients')
                ->title('Could not identify a recipient.')
                ->warning()
                ->send();

            return;
        }

        foreach ($recipients as $recipient) {
            $msg = new Message([
                'to_user_id' => $recipient->id,
                'subject' => $this->messageSubject,
                'body' => $this->messageBody,
            ]);
            $msg->from_user_id = $sender->id;
            $msg->save();
        }

        $this->showMessageModal = false;
        $this->messageBody = '';
        $this->messageSubject = '';

        $count = count($recipients);
        Notification::make('message-sent')
            ->title($count > 1 ? "Message sent to {$count} recipients." : 'Message sent.')
            ->success()
            ->send();
    }

    /**
     * @return User[]
     */
    private function resolveMessageRecipients(): array
    {
        return match ($this->messageRecipientType) {
            'author' => $this->selectedVersion?->contributor
                ? [$this->selectedVersion->contributor]
                : [],

            'subject_admin' => $this->record->subjectGrade->subjectAdmin
                ? [$this->record->subjectGrade->subjectAdmin]
                : [],

            'site_admin' => User::role('site_administrator')->where('is_system', false)->get()->all(),

            'any_user' => $this->messageToUserId
                ? User::where('id', $this->messageToUserId)->where('is_system', false)->get()->all()
                : [],

            default => [],
        };
    }

    private function buildMessageSubject(): string
    {
        $sg = $this->record->subjectGrade;

        return 'Question about '.$sg->subject->name
            .' Grade '.$sg->grade
            .' Day '.$this->record->day
            .' v'.($this->selectedVersion?->version ?? '?');
    }

    private function buildMessageBody(): string
    {
        $sg = $this->record->subjectGrade;
        $version = $this->selectedVersion;
        $url = LessonPlanFamilyResource::getUrl('view', ['record' => $this->record->id]);

        $context = "--- Lesson Context ---\n"
            ."Subject:     {$sg->subject->name}\n"
            ."Grade:       Grade {$sg->grade}\n"
            ."Day:         {$this->record->day}\n"
            .'Version:     v'.($version?->version ?? '?')."\n"
            .'Contributor: '.($version?->contributor?->name ?? '—')."\n"
            ."Link:        {$url}\n"
            ."----------------------\n\n";

        return $context;
    }

    // -------------------------------------------------------------------------
    // Email PDF
    // -------------------------------------------------------------------------

    public function openEmailPdfModal(): void
    {
        abort_unless(auth()->check(), 403);
        $this->showEmailPdfModal = true;
        $this->emailPdfTo = '';
        $this->emailPdfMessage = '';
    }

    public function sendEmailPdf(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'emailPdfTo' => 'required|email|max:255',
        ]);

        if (! $this->selectedVersion) {
            return;
        }

        $version = $this->selectedVersion;
        $version->load(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        try {
            $pdfContent = app(LessonPlanPdfService::class)->render($version->family, $version);

            Mail::to($this->emailPdfTo)->send(new LessonPlanPdfMail(
                version: $version,
                pdfContent: $pdfContent,
                senderName: auth()->user()->name,
                customMessage: $this->emailPdfMessage,
            ));

            $this->showEmailPdfModal = false;
            $this->emailPdfTo = '';
            $this->emailPdfMessage = '';

            Notification::make('email-pdf-sent')
                ->title('PDF sent successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make('email-pdf-failed')
                ->title('Failed to send PDF.')
                ->body('Please try again or contact the site administrator.')
                ->danger()
                ->send();
        }
    }
}
