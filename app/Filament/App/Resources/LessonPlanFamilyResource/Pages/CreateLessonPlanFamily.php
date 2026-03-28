<?php

namespace App\Filament\App\Resources\LessonPlanFamilyResource\Pages;

use App\Filament\App\Resources\LessonPlanFamilyResource;
use App\Models\LessonPlanFamily;
use App\Models\Message;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\VersionService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use League\HTMLToMarkdown\HtmlConverter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpWord\IOFactory;

class CreateLessonPlanFamily extends CreateRecord
{
    protected static string $resource = LessonPlanFamilyResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $englishId = Subject::where('name', 'English')->value('id');

        return $schema->schema([

            // ── Row 1: Subject · Grade · Day ─────────────────────────────────
            Grid::make(3)
                ->schema([
                    Select::make('subject_id')
                        ->label('Subject')
                        ->options(function () use ($user) {
                            $query = Subject::where('name', '!=', 'Kiswahili')->orderBy('name');

                            if (! $user->isSiteAdmin()) {
                                $allowedIds = SubjectGrade::where('subject_admin_user_id', $user->id)
                                    ->pluck('subject_id');
                                $query->whereIn('id', $allowedIds);
                            }

                            return $query->pluck('name', 'id');
                        })
                        ->default($englishId)
                        ->required()
                        ->live()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Subject name')
                                ->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => Subject::create(['name' => $data['name']])->id)
                        ->afterStateUpdated(fn (Set $set) => $set('grade', null)),

                    Select::make('grade')
                        ->label('Grade')
                        ->options([10 => 'Grade 10', 11 => 'Grade 11', 12 => 'Grade 12'])
                        ->default(10)
                        ->required()
                        ->live()
                        ->createOptionForm([
                            TextInput::make('grade')
                                ->label('Grade number')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) $data['grade']),

                    Select::make('day')
                        ->label('Day')
                        ->options(array_combine(range(1, 20), range(1, 20)))
                        ->default(1)
                        ->required()
                        ->live()
                        ->createOptionForm([
                            TextInput::make('day')
                                ->label('Day number')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) $data['day']),
                ]),

            // ── Row 2: Version · Major Revision · Minor Revision ──────────────
            Grid::make(3)
                ->schema([
                    Select::make('version_number')
                        ->label('Version')
                        ->options(array_combine(range(1, 9), range(1, 9)))
                        ->default(1)
                        ->required()
                        ->live()
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
                        ->live()
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
                        ->live()
                        ->createOptionForm([
                            TextInput::make('version_minor')
                                ->label('Minor revision number')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => (int) $data['version_minor']),
                ]),

            // ── Content ───────────────────────────────────────────────────────
            FileUpload::make('lesson_file')
                ->label('Upload file (optional)')
                ->helperText('Upload a .md or .txt file to populate the editor below, or a .docx Word document to convert to Markdown. You can edit the result before saving.')
                ->placeholder('Drag & Drop your file or Browse')
                ->acceptedFileTypes([
                    'text/plain',
                    'text/markdown',
                    'text/x-markdown',
                    '.md',
                    '.txt',
                    '.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->maxSize(32768)
                ->maxFiles(1)
                ->dehydrated(false)
                ->live()
                ->disabled(fn (): bool => ! $this->allMetadataFilled())
                ->columnSpanFull()
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    if (is_string($state)) {
                        $state = TemporaryUploadedFile::createFromLivewire($state);
                    }

                    if (! $state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    // Auto-populate Day and version fields from canonical filenames:
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

            TextInput::make('revision_note')
                ->label('Revision Note (optional)')
                ->placeholder('Brief note about this version')
                ->columnSpanFull(),
        ]);
    }

    /**
     * All six metadata fields must have a value before the file upload
     * widget and the submit button are enabled.
     */
    private function allMetadataFilled(): bool
    {
        $d = $this->data ?? [];

        return ! empty($d['subject_id'])
            && isset($d['grade']) && $d['grade'] !== '' && $d['grade'] !== null
            && isset($d['day']) && $d['day'] !== '' && $d['day'] !== null
            && isset($d['version_number']) && $d['version_number'] !== '' && $d['version_number'] !== null
            && isset($d['version_major']) && $d['version_major'] !== '' && $d['version_major'] !== null
            && isset($d['version_minor']) && $d['version_minor'] !== '' && $d['version_minor'] !== null;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Upload Lesson Plan')
            ->disabled(fn (): bool => ! $this->allMetadataFilled());
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        // Resolve subject_grade_id from subject + grade number
        $subjectGrade = SubjectGrade::where('subject_id', $data['subject_id'])
            ->where('grade', (int) $data['grade'])
            ->first();

        if (! $subjectGrade) {
            if ($user->isSiteAdmin()) {
                $subjectGrade = SubjectGrade::create([
                    'subject_id' => $data['subject_id'],
                    'grade' => (int) $data['grade'],
                ]);
            } else {
                abort(422, 'This subject/grade combination is not available.');
            }
        }

        if (! $user->isSiteAdmin()) {
            abort_unless($user->isSubjectAdminFor($subjectGrade), 403);
        }

        $versionString = $data['version_number'].'.'.$data['version_major'].'.'.$data['version_minor'];

        try {
            $version = app(VersionService::class)->createFamilyWithFirstVersion(
                $subjectGrade->id,
                (string) $data['day'],
                $data['content'],
                $data['revision_note'] ?? null,
                $user,
                $versionString
            );

            $this->createdFamilyId = $version->lesson_plan_family_id;

            return LessonPlanFamily::findOrFail($version->lesson_plan_family_id);

        } catch (UniqueConstraintViolationException) {
            $existing = LessonPlanFamily::where('subject_grade_id', $subjectGrade->id)
                ->where('day', $data['day'])
                ->first();

            if ($existing) {
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
