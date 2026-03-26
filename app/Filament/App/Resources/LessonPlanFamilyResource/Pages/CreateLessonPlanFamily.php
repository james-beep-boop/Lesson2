<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\Message;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\VersionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use League\HTMLToMarkdown\HtmlConverter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpWord\IOFactory;

class CreateLessonPlanFamily extends CreateRecord
{
    protected static string $resource = LessonPlanFamilyResource::class;

    // No "Save & Create Another" needed for lesson plans
    protected static bool $canCreateAnother = false;

    /**
     * Extend the resource form with content and revision note fields.
     * These two fields are only used during creation (content lives on the
     * version, not the family) and are extracted in handleRecordCreation().
     */
    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema->schema([
            Select::make('subject_grade_id')
                ->label('Subject Grade')
                ->options(
                    SubjectGrade::query()
                        ->when(
                            ! $user->isSiteAdmin(),
                            fn ($q) => $q->where('subject_admin_user_id', $user->id)
                        )
                        ->with('subject')
                        ->get()
                        ->mapWithKeys(fn ($sg) => [$sg->id => $sg->subject->name.' — Grade '.$sg->grade])
                )
                ->required()
                ->searchable(),

            TextInput::make('day')
                ->label('Day')
                ->required()
                ->placeholder('e.g. 1'),

            TextInput::make('strand_number')
                ->label('Strand Number')
                ->numeric()
                ->required(),

            TextInput::make('strand_name')
                ->label('Strand Name')
                ->required(),

            TextInput::make('substrand_number')
                ->label('Substrand Number')
                ->numeric()
                ->required(),

            TextInput::make('substrand_name')
                ->label('Substrand Name')
                ->required(),

            FileUpload::make('lesson_file')
                ->label('Upload file (optional)')
                ->helperText('Upload a .md or .txt file to populate the editor below, or a .docx Word document to convert to Markdown. You can edit the result before saving.')
                ->acceptedFileTypes([
                    'text/plain',
                    'text/markdown',
                    'text/x-markdown',
                    '.md',
                    '.txt',
                    '.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->maxSize(32768) // 32 MB — DreamHost upload_max_filesize
                ->dehydrated(false)
                ->live()
                ->columnSpanFull()
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    if (is_string($state)) {
                        $state = TemporaryUploadedFile::createFromLivewire($state);
                    }

                    if (! $state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    $ext = strtolower($state->getClientOriginalExtension());

                    if (in_array($ext, ['md', 'txt'])) {
                        $set('content', $state->get());

                        return;
                    }

                    if ($ext === 'docx') {
                        try {
                            set_time_limit(60);

                            $phpWord = IOFactory::load($state->getRealPath());
                            $writer = IOFactory::createWriter($phpWord, 'HTML');

                            ob_start();
                            $writer->save('php://output');
                            $html = ob_get_clean();

                            $converter = new HtmlConverter([
                                'strip_tags' => true,
                                'remove_nodes' => 'head style script',
                            ]);

                            $set('content', $converter->convert($html));

                            Notification::make()
                                ->title('Word document converted — please review')
                                ->body(
                                    'This system stores lesson plans as Markdown. '
                                    .'Complex formatting — tables, images, footnotes, and columns — may not have converted correctly. '
                                    .'Review the content carefully before saving.'
                                )
                                ->warning()
                                ->persistent()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Conversion failed')
                                ->body('The Word document could not be converted: '.$e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }
                }),

            Textarea::make('content')
                ->label('Lesson Plan Content (Markdown)')
                ->rows(20)
                ->required()
                ->columnSpanFull()
                ->placeholder('Write or paste your lesson plan in Markdown, or upload a file above...'),

            TextInput::make('revision_note')
                ->label('Revision Note (optional)')
                ->placeholder('Brief note about this version')
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $subjectGrade = SubjectGrade::findOrFail($data['subject_grade_id']);

        // Double-check Subject Admin scope (canCreate() already gated top-level access)
        if (! $user->isSiteAdmin()) {
            abort_unless($user->isSubjectAdminFor($subjectGrade), 403);
        }

        try {
            $version = app(VersionService::class)->createFamilyWithFirstVersion(
                (int) $data['subject_grade_id'],
                $data['day'],
                filled($data['strand_number']) ? (int) $data['strand_number'] : null,
                $data['strand_name'] ?? null,
                filled($data['substrand_number']) ? (int) $data['substrand_number'] : null,
                $data['substrand_name'] ?? null,
                $data['content'],
                $data['revision_note'] ?? null,
                $user
            );

            // Store the family id so getRedirectUrl() can build the view URL
            $this->createdFamilyId = $version->lesson_plan_family_id;

            return LessonPlanFamily::findOrFail($version->lesson_plan_family_id);

        } catch (UniqueConstraintViolationException) {
            $existing = LessonPlanFamily::where('subject_grade_id', $data['subject_grade_id'])
                ->where('day', $data['day'])
                ->first();

            if ($existing) {
                // Send a persistent inbox message from the System user so the
                // duplicate alert survives navigation and is findable in the inbox.
                $systemUser = User::where('is_system', true)->first();
                if ($systemUser) {
                    $message = new Message([
                        'to_user_id' => $user->id,
                        'subject' => 'Duplicate lesson plan detected',
                        'body' => 'A lesson plan already exists for this subject grade and day. '
                                     .'Your submission was not saved. '
                                     .'Open the existing plan and use "Edit This Plan" to add a new version instead.',
                    ]);
                    $message->from_user_id = $systemUser->id;
                    $message->save();
                }

                Notification::make('duplicate-family')
                    ->title('A lesson plan already exists for this subject grade and day.')
                    ->body('You\'ve been redirected to the existing lesson plan. Use "Edit This Plan" to add a new version.')
                    ->warning()
                    ->persistent()
                    ->send();

                $this->redirect(LessonPlanFamilyResource::getUrl('view', ['record' => $existing->id]));
            }

            throw new \RuntimeException('Family already exists but could not be found.');
        }
    }

    protected function getRedirectUrl(): string
    {
        return LessonPlanFamilyResource::getUrl('view', ['record' => $this->record->getKey()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lesson plan created.';
    }
}
