<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\Message;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\VersionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
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
            Select::make('subject_id')
                ->label('Subject')
                ->options(function () use ($user) {
                    $query = Subject::orderBy('name');

                    if (! $user->isSiteAdmin()) {
                        $allowedSubjectIds = SubjectGrade::where('subject_admin_user_id', $user->id)
                            ->pluck('subject_id');
                        $query->whereIn('id', $allowedSubjectIds);
                    }

                    return $query->pluck('name', 'id');
                })
                ->required()
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(fn (Set $set) => $set('subject_grade_id', null)),

            Select::make('subject_grade_id')
                ->label('Grade')
                ->options(function (Get $get) use ($user) {
                    $subjectId = $get('subject_id');
                    if (! $subjectId) {
                        return [];
                    }

                    return SubjectGrade::where('subject_id', $subjectId)
                        ->when(
                            ! $user->isSiteAdmin(),
                            fn ($q) => $q->where('subject_admin_user_id', $user->id)
                        )
                        ->orderBy('grade')
                        ->get()
                        ->mapWithKeys(fn ($sg) => [$sg->id => 'Grade '.$sg->grade]);
                })
                ->required()
                ->searchable(),

            Select::make('day')
                ->label('Day')
                ->options(array_combine(range(1, 20), range(1, 20)))
                ->required()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('day')
                        ->label('Day number')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->createOptionUsing(fn (array $data): int => (int) $data['day']),

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

                    // Auto-populate Day and version fields from filenames following the canonical convention:
                    // SUBJ_GRADE_DAY_REV_VER.MAJ.MIN.md  e.g. ENGL_10_1_REV_1.0.0.md
                    if (preg_match('/^[A-Z]{1,4}_\d+_(\d+)_REV_(\d+)\.(\d+)\.(\d+)\.md$/i', $state->getClientOriginalName(), $m)) {
                        $set('day', (int) $m[1]);
                        $set('version_number', (int) $m[2]);
                        $set('version_major', (int) $m[3]);
                        $set('version_minor', (int) $m[4]);
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

            Select::make('version_number')
                ->label('Version')
                ->options(array_combine(range(1, 9), range(1, 9)))
                ->default(1)
                ->required()
                ->createOptionForm([
                    TextInput::make('version_number')
                        ->label('Version number')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->createOptionUsing(fn (array $data): int => (int) $data['version_number']),

            Select::make('version_major')
                ->label('Major Revision')
                ->options(array_combine(range(0, 9), range(0, 9)))
                ->default(0)
                ->required()
                ->createOptionForm([
                    TextInput::make('version_major')
                        ->label('Major revision number')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->createOptionUsing(fn (array $data): int => (int) $data['version_major']),

            Select::make('version_minor')
                ->label('Minor Revision')
                ->options(array_combine(range(0, 9), range(0, 9)))
                ->default(0)
                ->required()
                ->createOptionForm([
                    TextInput::make('version_minor')
                        ->label('Minor revision number')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->createOptionUsing(fn (array $data): int => (int) $data['version_minor']),

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

        $versionString = $data['version_number'].'.'.$data['version_major'].'.'.$data['version_minor'];

        try {
            $version = app(VersionService::class)->createFamilyWithFirstVersion(
                (int) $data['subject_grade_id'],
                (string) $data['day'],
                $data['content'],
                $data['revision_note'] ?? null,
                $user,
                $versionString
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
