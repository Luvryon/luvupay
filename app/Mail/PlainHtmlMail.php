<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Transactional email. Callers MUST pass already-escaped HTML (use e()). */
class PlainHtmlMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        /** @var list<array{name:string,data:string,mime:string}> */
        public array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /** @return list<\Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $f) => \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $f['data'], $f['name'])->withMime($f['mime']),
            $this->files
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->bodyHtml);
    }
}
