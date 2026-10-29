<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

/**
 * Transactional HTML email.
 * Callers MUST pass already-escaped HTML (use e()).
 *
 * Optional $inline embeds (shown in body via cid:…):
 *   [['cid' => 'campaign-hero', 'data' => $binary, 'mime' => 'image/jpeg', 'name' => 'hero.jpg']]
 */
class PlainHtmlMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        /** @var list<array{name:string,data:string,mime:string}> */
        public array $files = [],
        /** @var list<array{cid:string,data:string,mime:string,name?:string}> */
        public array $inline = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $f) => Attachment::fromData(fn () => $f['data'], $f['name'])->withMime($f['mime']),
            $this->files
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->bodyHtml);
    }

    /** Embed inline images so clients do not need a public APP_URL to load them. */
    public function build(): static
    {
        if ($this->inline === []) {
            return $this;
        }

        return $this->withSymfonyMessage(function (Email $message) {
            foreach ($this->inline as $img) {
                $cid = (string) ($img['cid'] ?? 'inline');
                $data = (string) ($img['data'] ?? '');
                $mime = (string) ($img['mime'] ?? 'image/jpeg');
                $name = (string) ($img['name'] ?? ($cid.'.jpg'));
                if ($data === '') {
                    continue;
                }
                // Symfony uses the filename as Content-ID when embedding
                $message->embed($data, $name, $mime);
            }
        });
    }
}
