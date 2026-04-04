<?php

namespace App\Mail;

use App\Models\LessonPlanVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LessonPlanPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LessonPlanVersion $version,
        public readonly string $pdfContent,
        public readonly string $senderName,
        public readonly string $customMessage = '',
    ) {}

    public function envelope(): Envelope
    {
        $sg = $this->version->family->subjectGrade;
        $subject = $sg->subject->name.' — Grade '.$sg->grade
            .' Day '.$this->version->family->day
            .' v'.$this->version->version;

        return new Envelope(subject: 'Lesson Plan: '.$subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lesson-plan-pdf');
    }

    public function attachments(): array
    {
        $filename = str_replace('.md', '.pdf', $this->version->getFilename());

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
