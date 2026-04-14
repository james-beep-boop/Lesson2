<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Ai\Agents\LessonPlanAdvisor;
use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Mail\LessonPlanDocxMail;
use App\Mail\LessonPlanPdfMail;
use App\Models\Favorite;
use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Message;
use App\Models\User;
use App\Services\DeletionRequestService;
use App\Services\FavoriteService;
use App\Services\LessonPlanDocxService;
use App\Services\LessonPlanPdfService;
use App\Services\MarkdownNormalizer;
use App\Services\TranslationService;
use App\Services\VersionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Url;

class ViewLessonPlanFamily extends Page
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected string $view = 'filament.app.pages.view-lesson-plan-family';

    public LessonPlanFamily $record;

    #[Url(as: 'version')]
    public ?int $versionId = null;

    public ?LessonPlanVersion $selectedVersion = null;

    public ?LessonPlanVersion $compareVersion = null;

    public bool $compareMode = false;

    public bool $editMode = false;

    public string $editContent = '';

    public string $versionBump = 'patch';

    public ?string $revisionNote = null;

    public ?int $baseLatestVersionId = null;

    public ?Favorite $userFavorite = null;

    public bool $hasPendingDeletion = false;

    public bool $showDeletionForm = false;

    public string $deletionReason = '';

    // AI panel state
    public bool $aiPanelOpen = false;

    public string $aiPrompt = '';

    public string $aiResponse = '';

    public bool $aiResponseComplete = false;

    // Translation preview state
    public bool $translationPanelOpen = false;

    public string $translatedContent = '';

    public bool $translationComplete = false;

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
    // Email DOCX state
    // -------------------------------------------------------------------------

    public bool $showEmailDocxModal = false;

    public string $emailDocxTo = '';

    public string $emailDocxMessage = '';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function getTitle(): string
    {
        if ($this->compareMode) {
            $subjectGrade = $this->record->subjectGrade;

            return 'Compare Versions: '
                .$subjectGrade->subject->name
                .' Grade '
                .$subjectGrade->grade
                .' Day '
                .$this->record->day;
        }

        return 'View / Edit Lesson Plan';
    }

    public function mount(LessonPlanFamily $record): void
    {
        $this->record = $record->load(['versions.contributor', 'officialVersion', 'latestVersion', 'subjectGrade.subject', 'subjectGrade.subjectAdmin']);
        $this->selectedVersion = $this->versionId
            ? $record->versions->firstWhere('id', $this->versionId)
            : null;

        $this->selectedVersion ??= $record->officialVersion ?? $record->latestVersion;
        $this->syncDerivedState();
    }

    private function syncPendingDeletion(): void
    {
        $this->hasPendingDeletion = (bool) $this->selectedVersion?->deletionRequests()
            ->whereNull('resolved_at')
            ->exists();
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

        $this->syncPendingDeletion();
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

        $this->versionId = $version->id;
        $this->selectedVersion = $version;
        $this->compareMode = false;
        $this->compareVersion = null;
        $this->syncPendingDeletion();
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function enterEditMode(): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);
        $this->editContent = $this->selectedVersion?->content ?? '';
        $this->baseLatestVersionId = $this->record->latestVersion?->id;
        $this->editMode = true;
    }

    public function cancelEditMode(): void
    {
        $this->resetEditState();
    }

    public function saveNewVersion(VersionService $versionService): void
    {
        $this->authorize('create', [LessonPlanVersion::class, $this->record]);

        $this->validate([
            'editContent' => 'required|string',
            'versionBump' => 'required|in:patch,minor,major',
        ]);

        // Stale version guard — query fresh database state
        $freshLatestVersionId = $this->record->fresh(['latestVersion'])->latestVersion?->id;

        if ($freshLatestVersionId !== $this->baseLatestVersionId) {
            Notification::make('stale-version')
                ->title('The lesson plan was updated while you were editing.')
                ->body('A newer version exists. Copy any unsaved changes before refreshing.')
                ->warning()
                ->send();

            // Leave edit mode open — the user may have unsaved work to copy
            return;
        }

        $normalizer = app(MarkdownNormalizer::class);
        $normalized = $normalizer->normalize($this->editContent);
        $normalizedCurrent = $normalizer->normalize($this->selectedVersion?->content ?? '');

        // No-op check — don't create a version if content is unchanged
        if ($normalized === $normalizedCurrent) {
            Notification::make('no-change')
                ->title('No changes detected — content is identical to the current version.')
                ->info()
                ->send();
            $this->resetEditState();

            return;
        }

        $version = $versionService->addVersion(
            $this->record,
            $normalized,
            $this->versionBump,
            $this->revisionNote ?: null,
            auth()->user()
        );

        $this->record->refresh();
        $this->versionId = $version->id;
        $this->selectedVersion = $version;
        $this->hasPendingDeletion = false;
        $this->resetEditState();

        Notification::make('version-saved')->title('New version saved.')->success()->send();
    }

    private function resetEditState(): void
    {
        $this->editMode = false;
        $this->editContent = $this->selectedVersion?->content ?? '';
        $this->revisionNote = null;
        $this->versionBump = 'patch';
        $this->baseLatestVersionId = null;
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
        $version = $this->record->versions->find($versionId);

        if (! $version) {
            return;
        }

        $this->selectedVersion = $version;
        $this->versionId = $version->id;
        $this->compareVersion = $this->defaultCompareVersionFor($version) ?? $version;
        $this->compareMode = true;
    }

    private function defaultCompareVersionFor(LessonPlanVersion $selectedVersion): ?LessonPlanVersion
    {
        /** @var \Illuminate\Support\Collection<int, LessonPlanVersion> $orderedVersions */
        $orderedVersions = $this->record->versions
            ->sort(fn (LessonPlanVersion $left, LessonPlanVersion $right) => version_compare($left->version, $right->version))
            ->values();

        $selectedIndex = $orderedVersions->search(fn (LessonPlanVersion $version): bool => $version->id === $selectedVersion->id);

        if ($selectedIndex === false) {
            return null;
        }

        return $orderedVersions->get($selectedIndex - 1) ?? $orderedVersions->get($selectedIndex + 1);
    }

    public function selectCompareVersion(int $versionId): void
    {
        $version = $this->record->versions->find($versionId);

        if (! $version || ! $this->compareMode) {
            return;
        }

        $this->compareVersion = $version;
        // Push new content to the right viewer without remounting both panes.
        $this->dispatch('compare-right-updated', content: $version->content);
    }

    public function cancelCompare(): void
    {
        $this->compareMode = false;
        $this->compareVersion = null;
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

    public function closeAiPanel(): void
    {
        $this->aiPanelOpen = false;
        $this->aiResponse = '';
        $this->aiResponseComplete = false;
    }

    // -------------------------------------------------------------------------
    // Swahili translation preview
    // -------------------------------------------------------------------------

    public function openTranslationPanel(): void
    {
        $this->authorize('translate', $this->selectedVersion);

        $this->translatedContent = '';
        $this->translationComplete = false;
        $this->translationPanelOpen = true;

        // Dispatch a one-time event that Alpine listens for to start translation.
        // Using dispatch instead of x-init prevents re-firing on every re-render.
        $this->dispatch('start-translation');

        Notification::make('translation-started')
            ->title('Translation in progress')
            ->body('The Swahili translation will appear above the lesson content.')
            ->info()
            ->send();
    }

    public function closeTranslationPanel(): void
    {
        $this->translationPanelOpen = false;
        $this->translatedContent = '';
        $this->translationComplete = false;
        $this->showTranslationEmailPanel = false;
    }

    public function translatePreview(TranslationService $translationService): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('translate', $this->selectedVersion);

        set_time_limit(120);

        $this->translationComplete = false;

        try {
            $accumulated = '';

            foreach ($translationService->streamTranslation($this->selectedVersion->content) as $event) {
                if ($event instanceof TextDelta) {
                    $accumulated .= $event->delta;
                    $this->stream($event->delta, false, 'translatedContent');
                }
            }

            $this->translatedContent = $accumulated;
            $this->translationComplete = true;
        } catch (\Throwable $exception) {
            report($exception);

            Log::warning('Swahili translation failed.', [
                'lesson_plan_version_id' => $this->selectedVersion->id,
                'lesson_plan_family_id' => $this->record->id,
                'provider' => config('ai.default'),
                'message' => $exception->getMessage(),
            ]);

            $this->translationPanelOpen = false;
            $this->translatedContent = '';
            $this->translationComplete = false;

            Notification::make('translation-failed')
                ->title('Translation unavailable')
                ->body('The translation service could not complete the request. Please check the AI provider configuration, quota, or timeout and try again.')
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
        $this->aiResponseComplete = false;
        $accumulated = '';

        set_time_limit(120);

        try {
            $stream = LessonPlanAdvisor::make()->stream($prompt);

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $accumulated .= $event->delta;
                    $this->stream($event->delta, false, 'aiResponse');
                }
            }

            $this->aiResponse = $accumulated;
            $this->aiResponseComplete = true;
        } catch (\Throwable $exception) {
            report($exception);

            Log::warning('Lesson plan AI advice failed.', [
                'lesson_plan_version_id' => $this->selectedVersion->id,
                'lesson_plan_family_id' => $this->record->id,
                'provider' => config('ai.default'),
                'message' => $exception->getMessage(),
            ]);

            $this->aiResponse = '';
            $this->aiResponseComplete = false;

            Notification::make('ask-ai-failed')
                ->title('Ask AI unavailable')
                ->body('The AI service could not complete the request. Please check the AI provider configuration, quota, or timeout and try again.')
                ->danger()
                ->send();
        }
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
        $url = LessonPlanFamilyResource::viewUrl($this->record, $version);

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

    // -------------------------------------------------------------------------
    // Email DOCX
    // -------------------------------------------------------------------------

    public function openEmailDocxModal(): void
    {
        abort_unless(auth()->check(), 403);
        $this->showEmailDocxModal = true;
        $this->emailDocxTo = '';
        $this->emailDocxMessage = '';
    }

    public function sendEmailDocx(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'emailDocxTo' => 'required|email|max:255',
        ]);

        if (! $this->selectedVersion) {
            return;
        }

        $version = $this->selectedVersion;
        $version->load(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        try {
            $docxContent = app(LessonPlanDocxService::class)->render($version->family, $version);

            Mail::to($this->emailDocxTo)->send(new LessonPlanDocxMail(
                version: $version,
                docxContent: $docxContent,
                senderName: auth()->user()->name,
                customMessage: $this->emailDocxMessage,
            ));

            $this->showEmailDocxModal = false;
            $this->emailDocxTo = '';
            $this->emailDocxMessage = '';

            Notification::make('email-docx-sent')
                ->title('.docx sent successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make('email-docx-failed')
                ->title('Failed to send .docx.')
                ->body('Please try again or contact the site administrator.')
                ->danger()
                ->send();
        }
    }
}
