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
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Layout for the diff panes: 'side-by-side' or 'stacked'. */
    public string $diffLayout = 'side-by-side';

    public bool $editMode = false;

    public string $editContent = '';

    public string $versionBump = 'patch';

    public ?string $revisionNote = null;

    public ?int $baseLatestVersionId = null;

    public ?Favorite $userFavorite = null;

    public bool $hasPendingDeletion = false;

    // AI panel state
    public bool $aiPanelOpen = false;

    public string $aiPrompt = '';

    public string $aiResponse = '';

    public bool $aiResponseComplete = false;

    // Translation preview state
    public bool $translationPanelOpen = false;

    /** Per-render cache for resolveMessageRecipientsFor() — avoids repeat DB queries. */
    private array $resolvedMessageRecipients = [];

    public string $translatedContent = '';

    public bool $translationComplete = false;

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

        $this->userFavorite = $favoriteService->toggle(auth()->user(), $this->selectedVersion);

        Notification::make('favorited')
            ->title($this->userFavorite ? 'Added to favorites.' : 'Removed from favorites.')
            ->success()
            ->send();
    }

    /** Returns ['major' => '2.0.0', 'minor' => '1.1.0', 'patch' => '1.0.1'] based on current versions. */
    public function versionPreviews(): array
    {
        return app(VersionService::class)->computeAllNextVersions($this->record);
    }

    protected function submitDeletionRequest(?string $reason = null): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('requestDeletion', $this->selectedVersion);

        if (! $this->canRequestDeletionAction()) {
            return;
        }

        $user = auth()->user();

        if ($this->hasPendingDeletion) {
            Notification::make('deletion-already-pending')
                ->title('A pending deletion request already exists for this version.')
                ->warning()
                ->send();

            return;
        }

        app(DeletionRequestService::class)->request(
            $this->selectedVersion,
            $user,
            filled($reason) ? $reason : null
        );

        $this->hasPendingDeletion = true;

        Notification::make('deletion-requested')
            ->title('Deletion request submitted — the contributor, Subject Admin (if assigned), and all Site Admins have been notified.')
            ->success()
            ->send();
    }

    protected function performDirectDeleteVersion(): void
    {
        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('directDelete', $this->selectedVersion);

        if (! $this->canDeleteSelectedVersion()) {
            return;
        }

        $version = $this->selectedVersion;
        $family = $this->record;

        $version->delete();

        $family->refresh();

        if ($family->versions()->doesntExist()) {
            $family->delete();

            $this->redirect(LessonPlanFamilyResource::getUrl('index'));

            return;
        }

        // If deleted version was official, pick a new one.
        if ((int) $family->official_version_id === $version->id) {
            $versionService = app(VersionService::class);

            $versionService->setOfficialVersion(
                $family,
                $versionService->preferredOfficialVersion($family, $version->id)
            );
        }

        $this->record = $family->load(['versions.contributor', 'officialVersion', 'latestVersion', 'subjectGrade.subject', 'subjectGrade.subjectAdmin']);
        $this->selectedVersion = $family->officialVersion ?? $family->latestVersion;
        $this->versionId = $this->selectedVersion?->id;
        $this->syncDerivedState();

        Notification::make('version-deleted')
            ->title('Version deleted.')
            ->success()
            ->send();
    }

    // -------------------------------------------------------------------------
    // Compare / visual diff
    // -------------------------------------------------------------------------

    public function warnCannotCompare(): void
    {
        Notification::make('cannot-compare')
            ->title('At least 2 versions are needed to compare.')
            ->warning()
            ->send();
    }

    /**
     * Enter compare mode, setting $versionId as the comparison target.
     * The selected version becomes the base (left pane) and we preselect
     * an adjacent version for the comparison target (right pane).
     */
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
        $orderedVersions = $this->record->versions
            ->sort(fn (LessonPlanVersion $left, LessonPlanVersion $right) => version_compare($left->version, $right->version))
            ->values();

        $selectedIndex = $orderedVersions->search(
            fn (LessonPlanVersion $version): bool => $version->id === $selectedVersion->id
        );

        if ($selectedIndex === false) {
            return null;
        }

        return $orderedVersions->get($selectedIndex - 1) ?? $orderedVersions->get($selectedIndex + 1);
    }

    /**
     * Compare the currently viewed version against the version immediately
     * before it in semver order. Shows a warning if no previous version exists.
     */
    public function compareToPreviousVersion(): void
    {
        $orderedVersions = $this->record->versions
            ->sort(fn (LessonPlanVersion $left, LessonPlanVersion $right) => version_compare($left->version, $right->version))
            ->values();

        $selectedIndex = $orderedVersions->search(
            fn (LessonPlanVersion $version): bool => $version->id === $this->selectedVersion->id
        );

        $previousVersion = ($selectedIndex !== false && $selectedIndex > 0)
            ? $orderedVersions->get($selectedIndex - 1)
            : null;

        if (! $previousVersion) {
            Notification::make('no-previous-version')
                ->title('No previous version to compare.')
                ->warning()
                ->send();

            return;
        }

        $this->compareVersion = $previousVersion;
        $this->compareMode = true;
    }

    /**
     * Compare the currently viewed version against the official version.
     * Shows a warning if no official version is set.
     */
    public function compareToOfficialVersion(): void
    {
        $officialVersion = $this->record->official_version_id
            ? $this->record->versions->find($this->record->official_version_id)
            : null;

        if (! $officialVersion) {
            Notification::make('no-official-version')
                ->title('No official version is set for this lesson plan.')
                ->warning()
                ->send();

            return;
        }

        $this->compareVersion = $officialVersion;
        $this->compareMode = true;
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

    /**
     * Toggle the diff pane layout between 'side-by-side' and 'stacked'.
     * Note: scroll-sync between panes is only active in side-by-side mode.
     */
    public function toggleDiffLayout(): void
    {
        $this->diffLayout = $this->diffLayout === 'side-by-side' ? 'stacked' : 'side-by-side';
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
    }

    public function emailTranslationPdfAction(): Action
    {
        return Action::make('emailTranslationPdf')
            ->authorize(fn (): bool => $this->selectedVersion instanceof LessonPlanVersion
                && auth()->check()
                && auth()->user()->can('translate', $this->selectedVersion))
            ->hidden(fn (): bool => ! $this->canUseTranslatedPreviewActions())
            ->modalHeading('Email Swahili Translation PDF')
            ->modalSubmitActionLabel('Send PDF')
            ->schema($this->emailActionSchema())
            ->action(fn (array $data): mixed => $this->sendTranslationEmailPdf(
                emailTo: $data['email'],
                message: $data['message'] ?? null,
            ));
    }

    /**
     * Prepare a short-lived inline PDF URL for browser printing.
     */
    public function preparePrintTranslation(): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('translate', $this->selectedVersion);

        if (! $this->canUseTranslatedPreviewActions()) {
            return;
        }

        $token = (string) Str::uuid();

        Cache::put("translation-preview-pdf:{$token}", [
            'user_id' => auth()->id(),
            'lesson_plan_version_id' => $this->selectedVersion->id,
            'translated_content' => $this->translatedContent,
        ], now()->addMinutes(5));

        $this->dispatch('open-translation-print', url: route('lesson-plan.translation-preview-pdf', ['token' => $token]));
    }

    public function downloadTranslationPdf(): ?StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        if (! $this->selectedVersion) {
            return null;
        }

        $this->authorize('translate', $this->selectedVersion);

        if (! $this->canUseTranslatedPreviewActions()) {
            return null;
        }

        try {
            ['bytes' => $pdfContent, 'filename' => $filename] = $this->buildTranslationPdf();

            return response()->streamDownload(function () use ($pdfContent): void {
                echo $pdfContent;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Notification::make('translation-download-error')
                ->title('Failed to download translation PDF.')
                ->body('Please try again or contact the site administrator.')
                ->danger()
                ->send();

            return null;
        }
    }

    public function useAiPrompt(string $prompt): void
    {
        $this->aiPrompt = $prompt;
        $this->submitAiPrompt();
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
            $this->translatedContent = $translationService->translatePreservingMarkdown($this->selectedVersion->content);
            $this->translationComplete = true;
        } catch (\Throwable $exception) {
            $this->logAiFailure('Swahili translation failed.', $exception);

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

    protected function canUseTranslatedPreviewActions(): bool
    {
        return auth()->check()
            && $this->translationPanelOpen
            && $this->selectedVersion instanceof LessonPlanVersion
            && auth()->user()->can('translate', $this->selectedVersion)
            && $this->translationComplete
            && filled($this->translatedContent);
    }

    protected function sendTranslationEmailPdf(string $emailTo, ?string $message = null): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->selectedVersion) {
            return;
        }

        $this->authorize('translate', $this->selectedVersion);

        if (! $this->canUseTranslatedPreviewActions()) {
            return;
        }

        try {
            ['bytes' => $pdfContent] = $this->buildTranslationPdf();

            Mail::to($emailTo)->send(new LessonPlanPdfMail(
                version: $this->selectedVersion,
                pdfContent: $pdfContent,
                senderName: auth()->user()->name,
                customMessage: 'Swahili translation — preview only, not saved to database.'
                    .($message ? "\n\n".$message : ''),
            ));

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

    /**
     * @return array{bytes: string, filename: string}
     */
    protected function buildTranslationPdf(): array
    {
        /** @var LessonPlanVersion $version */
        $version = $this->selectedVersion;
        $version->loadMissing(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        $pdf = app(LessonPlanPdfService::class);

        return [
            'bytes' => $pdf->renderTranslation(
                $version->family,
                $version,
                $this->translatedContent,
            ),
            'filename' => $pdf->translationFilename($version),
        ];
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
            $this->logAiFailure('Lesson plan AI advice failed.', $exception);

            $this->aiResponse = '';
            $this->aiResponseComplete = false;

            Notification::make('ask-ai-failed')
                ->title('Ask AI unavailable')
                ->body('The AI service could not complete the request. Please check the AI provider configuration, quota, or timeout and try again.')
                ->danger()
                ->send();
        }
    }

    protected function logAiFailure(string $message, \Throwable $exception): void
    {
        report($exception);

        $previous = $exception->getPrevious();

        Log::warning($message, [
            'lesson_plan_version_id' => $this->selectedVersion?->id,
            'lesson_plan_family_id' => $this->record->id,
            'provider' => config('ai.default'),
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'previous_exception_class' => $previous ? get_class($previous) : null,
            'previous_exception_code' => $previous?->getCode(),
            'previous_exception_message' => $previous?->getMessage(),
            'response_body' => $this->extractExceptionResponseBody($exception),
        ]);
    }

    protected function extractExceptionResponseBody(\Throwable $exception): mixed
    {
        foreach (['response', 'getResponse', 'body', 'getBody'] as $method) {
            if (! method_exists($exception, $method)) {
                continue;
            }

            try {
                $value = $exception->{$method}();
            } catch (\Throwable) {
                continue;
            }

            if (is_scalar($value) || is_array($value)) {
                return $value;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Lesson-context messaging
    // -------------------------------------------------------------------------

    public function messageAboutThisActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->messageAuthorAction(),
            $this->messageSubjectAdminAction(),
            $this->messageSiteAdminAction(),
        ])
            ->label('Message About This')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->size('sm')
            ->button()
            ->dropdownPlacement('bottom-start');
    }

    public function messageAuthorAction(): Action
    {
        return $this->makeLessonMessageAction(
            name: 'messageAuthor',
            recipientType: 'author',
            modalHeading: 'Message Author About This Lesson',
        );
    }

    public function messageSubjectAdminAction(): Action
    {
        return $this->makeLessonMessageAction(
            name: 'messageSubjectAdmin',
            recipientType: 'subject_admin',
            modalHeading: 'Message Subject Admin About This Lesson',
        );
    }

    public function messageSiteAdminAction(): Action
    {
        return $this->makeLessonMessageAction(
            name: 'messageSiteAdmin',
            recipientType: 'site_admin',
            modalHeading: 'Message Site Administrator About This Lesson',
        );
    }

    protected function makeLessonMessageAction(string $name, string $recipientType, string $modalHeading): Action
    {
        return Action::make($name)
            ->authorize(fn (): bool => $this->selectedVersion instanceof LessonPlanVersion
                && auth()->check()
                && ! auth()->user()->is_system)
            ->visible(fn (): bool => $this->canMessageRecipientType($recipientType))
            ->modalHeading($modalHeading)
            ->modalDescription(fn (): string => 'To: '.$this->messageRecipientLabel($recipientType))
            ->modalSubmitActionLabel('Send Message')
            ->fillForm(fn (): array => [
                'subject' => $this->buildMessageSubject(),
                'body' => $this->buildMessageBody(),
            ])
            ->schema($this->lessonMessageSchema())
            ->action(fn (array $data): mixed => $this->sendLessonMessageTo(
                recipientType: $recipientType,
                subject: $data['subject'],
                body: $data['body'],
            ));
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    protected function lessonMessageSchema(): array
    {
        return [
            TextInput::make('subject')
                ->label('Subject')
                ->required()
                ->maxLength(255),
            Textarea::make('body')
                ->label('Message')
                ->required()
                ->rows(10),
        ];
    }

    protected function sendLessonMessageTo(string $recipientType, string $subject, string $body): void
    {
        abort_unless(auth()->check() && ! auth()->user()->is_system, 403);

        $recipients = $this->resolveMessageRecipientsFor($recipientType);

        if (empty($recipients)) {
            Notification::make('message-no-recipients')
                ->title('Could not identify a recipient.')
                ->warning()
                ->send();

            return;
        }

        $sender = auth()->user();

        foreach ($recipients as $recipient) {
            $message = new Message([
                'to_user_id' => $recipient->id,
                'subject' => $subject,
                'body' => $body,
            ]);
            $message->from_user_id = $sender->id;
            $message->save();
        }

        $count = count($recipients);
        Notification::make('message-sent')
            ->title($count > 1 ? "Message sent to {$count} recipients." : 'Message sent.')
            ->success()
            ->send();
    }

    protected function canMessageRecipientType(string $recipientType): bool
    {
        return auth()->check()
            && ! auth()->user()->is_system
            && $this->selectedVersion instanceof LessonPlanVersion
            && filled($this->resolveMessageRecipientsFor($recipientType));
    }

    /**
     * @return User[]
     */
    protected function resolveMessageRecipientsFor(string $recipientType): array
    {
        return $this->resolvedMessageRecipients[$recipientType] ??= match ($recipientType) {
            'author' => $this->selectedVersion?->contributor
                ? [$this->selectedVersion->contributor]
                : [],

            'subject_admin' => ($subjectAdmin = $this->record->subjectGrade->subjectAdmin)
                && ($subjectAdmin->id !== auth()->id())
                ? [$subjectAdmin]
                : [],

            'site_admin' => User::role('site_administrator')
                ->where('is_system', false)
                ->where('id', '!=', auth()->id())
                ->get()
                ->all(),

            default => [],
        };
    }

    protected function messageRecipientLabel(string $recipientType): string
    {
        return match ($recipientType) {
            'author' => $this->selectedVersion?->contributor?->name ?? 'Unknown author',
            'subject_admin' => $this->record->subjectGrade->subjectAdmin?->name ?? 'No subject administrator assigned',
            'site_admin' => 'All Site Administrators',
            default => 'Unknown recipient',
        };
    }

    protected function buildMessageSubject(): string
    {
        $sg = $this->record->subjectGrade;

        return 'Question about '.$sg->subject->name
            .' Grade '.$sg->grade
            .' Day '.$this->record->day
            .' v'.($this->selectedVersion?->version ?? '?');
    }

    protected function buildMessageBody(): string
    {
        $sg = $this->record->subjectGrade;
        $version = $this->selectedVersion;

        $linkLabel = $sg->subject->name
            .' Grade '.$sg->grade
            .' Day '.$this->record->day
            .' version '.($version?->version ?? '?');

        $url = $version
            ? LessonPlanFamilyResource::versionUrl($version)
            : LessonPlanFamilyResource::getUrl('view', ['record' => $this->record->id]);

        return 'Subject: '.$sg->subject->name
            .' Grade '.$sg->grade
            .' Day '.$this->record->day
            .' Version v'.($version?->version ?? '?')."\n"
            .'Contributor: '.($version?->contributor?->name ?? '—')."\n\n"
            .'---'."\n"
            .'Lesson plan: ['.$linkLabel.']('.$url.')';
    }

    // -------------------------------------------------------------------------
    // Email PDF / DOCX
    // -------------------------------------------------------------------------

    public function emailPdfAction(): Action
    {
        return Action::make('emailPdf')
            ->authorize(fn (): bool => $this->canEmailCurrentVersion())
            ->modalHeading('Email PDF')
            ->modalSubmitActionLabel('Send PDF')
            ->schema($this->emailActionSchema())
            ->action(fn (array $data): mixed => $this->sendLessonPlanPdfEmail(
                emailTo: $data['email'],
                message: $data['message'] ?? null,
            ));
    }

    public function emailDocxAction(): Action
    {
        return Action::make('emailDocx')
            ->authorize(fn (): bool => $this->canEmailCurrentVersion())
            ->modalHeading('Email .docx')
            ->modalSubmitActionLabel('Send .docx')
            ->schema($this->emailActionSchema())
            ->action(fn (array $data): mixed => $this->sendLessonPlanDocxEmail(
                emailTo: $data['email'],
                message: $data['message'] ?? null,
            ));
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    protected function emailActionSchema(): array
    {
        return [
            TextInput::make('email')
                ->label('Recipient Email')
                ->email()
                ->required()
                ->maxLength(255),
            Textarea::make('message')
                ->label('Optional message')
                ->rows(3)
                ->maxLength(5000)
                ->placeholder('Add a note to include in the email body…'),
        ];
    }

    protected function sendLessonPlanPdfEmail(string $emailTo, ?string $message = null): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->canEmailCurrentVersion()) {
            return;
        }

        try {
            ['bytes' => $pdfContent] = $this->buildLessonPlanPdf();

            Mail::to($emailTo)->send(new LessonPlanPdfMail(
                version: $this->selectedVersion,
                pdfContent: $pdfContent,
                senderName: auth()->user()->name,
                customMessage: $message ?? '',
            ));

            Notification::make('email-pdf-sent')
                ->title('PDF sent successfully.')
                ->success()
                ->send();
        } catch (\Throwable) {
            Notification::make('email-pdf-failed')
                ->title('Failed to send PDF.')
                ->body('Please try again or contact the site administrator.')
                ->danger()
                ->send();
        }
    }

    protected function sendLessonPlanDocxEmail(string $emailTo, ?string $message = null): void
    {
        abort_unless(auth()->check(), 403);

        if (! $this->canEmailCurrentVersion()) {
            return;
        }

        try {
            ['bytes' => $docxContent] = $this->buildLessonPlanDocx();

            Mail::to($emailTo)->send(new LessonPlanDocxMail(
                version: $this->selectedVersion,
                docxContent: $docxContent,
                senderName: auth()->user()->name,
                customMessage: $message ?? '',
            ));

            Notification::make('email-docx-sent')
                ->title('.docx sent successfully.')
                ->success()
                ->send();
        } catch (\Throwable) {
            Notification::make('email-docx-failed')
                ->title('Failed to send .docx.')
                ->body('Please try again or contact the site administrator.')
                ->danger()
                ->send();
        }
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    protected function buildLessonPlanPdf(): array
    {
        /** @var LessonPlanVersion $version */
        $version = $this->selectedVersion;
        $version->loadMissing(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        $pdf = app(LessonPlanPdfService::class);

        return [
            'bytes' => $pdf->render($version->family, $version),
            'filename' => $pdf->filename($version),
        ];
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    protected function buildLessonPlanDocx(): array
    {
        /** @var LessonPlanVersion $version */
        $version = $this->selectedVersion;
        $version->loadMissing(['family.subjectGrade.subject', 'contributor']);

        set_time_limit(60);

        $docx = app(LessonPlanDocxService::class);

        return [
            'bytes' => $docx->render($version->family, $version),
            'filename' => $docx->filename($version),
        ];
    }

    protected function canEmailCurrentVersion(): bool
    {
        return auth()->check() && $this->selectedVersion instanceof LessonPlanVersion;
    }

    // -------------------------------------------------------------------------
    // Deletion actions
    // -------------------------------------------------------------------------

    public function requestDeletionAction(): Action
    {
        return Action::make('requestDeletion')
            ->authorize(fn (): bool => $this->canRequestDeletionAction())
            ->modalHeading(fn (): string => 'Request Deletion of Version '.($this->selectedVersion?->version ?? '?'))
            ->modalDescription('A Site Administrator must approve and carry out the actual deletion. The contributor, Subject Admin (if assigned), and all Site Admins will be notified by inbox message.')
            ->modalSubmitActionLabel('Submit Request')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->rows(3)
                    ->placeholder('Explain why this version should be deleted…'),
            ])
            ->action(fn (array $data): mixed => $this->submitDeletionRequest($data['reason'] ?? null));
    }

    public function deleteVersionAction(): Action
    {
        return Action::make('deleteVersion')
            ->authorize(fn (): bool => $this->canDeleteSelectedVersion())
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(fn (): string => 'Delete Version '.($this->selectedVersion?->version ?? '?'))
            ->modalDescription('This will permanently delete this version. This action cannot be undone.')
            ->modalSubmitActionLabel('Confirm Delete')
            ->action(fn (): mixed => $this->performDirectDeleteVersion());
    }

    protected function canRequestDeletionAction(): bool
    {
        return auth()->check()
            && $this->selectedVersion instanceof LessonPlanVersion
            && auth()->user()->can('requestDeletion', $this->selectedVersion)
            && ! auth()->user()->can('directDelete', $this->selectedVersion)
            && ! $this->hasPendingDeletion
            && ! $this->isOfficialVersionSelected();
    }

    protected function canDeleteSelectedVersion(): bool
    {
        return auth()->check()
            && $this->selectedVersion instanceof LessonPlanVersion
            && auth()->user()->can('directDelete', $this->selectedVersion);
    }

    protected function isOfficialVersionSelected(): bool
    {
        return $this->selectedVersion instanceof LessonPlanVersion
            && (int) $this->record->official_version_id === $this->selectedVersion->id;
    }
}
