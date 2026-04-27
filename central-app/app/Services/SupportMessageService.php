<?php

namespace App\Services;

use App\Mail\SupportMessageMail;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class SupportMessageService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function send(string $source, array $payload, array $metadata = []): void
    {
        $recipient = $this->resolveRecipient($source);

        Mail::to($recipient)->send(new SupportMessageMail($source, $payload, $metadata));
    }

    protected function resolveRecipient(string $source): string
    {
        $recipient = match ($source) {
            'central' => (string) config('support.central_recipient'),
            'tenant' => (string) config('support.tenant_recipient'),
            default => throw new InvalidArgumentException(sprintf('Unsupported support source [%s].', $source)),
        };

        return $recipient !== '' ? $recipient : (string) config('support.default_recipient');
    }
}
