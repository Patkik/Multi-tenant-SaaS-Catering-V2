<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class SupportMessageMail extends Mailable
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $supportMetadata
     */
    public function __construct(
        public readonly string $source,
        public readonly array $payload,
        public readonly array $supportMetadata = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->formatSubjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-message',
            with: [
                'source' => $this->source,
                'payload' => $this->payload,
                'supportMetadata' => $this->supportMetadata,
                'subjectLine' => $this->formatSubjectLine(),
            ],
        );
    }

    protected function formatSubjectLine(): string
    {
        $scopeLabel = Str::title(str_replace(['-', '_'], ' ', $this->source));
        $category = Str::title((string) ($this->payload['category'] ?? 'support'));
        $subject = trim((string) ($this->payload['subject'] ?? ''));

        return sprintf('[%s] %s: %s', $scopeLabel, $category, Str::limit($subject !== '' ? $subject : 'Support request', 80));
    }
}
