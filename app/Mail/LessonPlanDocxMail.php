<?php

namespace App\Mail;

use App\Models\LessonPlanVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LessonPlanDocxMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LessonPlanVersion $version,
        public readonly string $docxContent,
        public readonly string $senderName,
        public readonly string $customMessage = '',
    ) {}

    public function envelope(): Envelope
    {
        $sg = $this->version->family->subjectGrade;
        $subject = $sg->subject->name.' — Grade '.$sg->grade
            .' Day '.$this->version->family->day
            .' v'.$this->version->version;

        return new Envelope(subject: 'Lesson Plan (.docx): '.$subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lesson-plan-docx');
    }

    public function attachments(): array
    {
        $filename = str_replace('.md', '.docx', $this->version->getFilename());

        return [
            Attachment::fromData(fn () => $this->docxContent, $filename)
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
