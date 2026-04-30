<?php

namespace App\Services;

use App\Mail\SupportMessageMail;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        $this->storeMessage($source, $payload, $metadata);

        $recipient = $this->resolveRecipient($source);

        Mail::to($recipient)->send(new SupportMessageMail($source, $payload, $metadata));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    protected function storeMessage(string $source, array $payload, array $metadata): void
    {
        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        DB::connection($centralConnection)
            ->table('support_messages')
            ->insert([
                'source' => $source,
                'category' => (string) Arr::get($payload, 'category', 'feedback'),
                'subject' => (string) Arr::get($payload, 'subject', ''),
                'message' => (string) Arr::get($payload, 'message', ''),
                'contact_name' => Arr::get($metadata, 'contact_name', Arr::get($payload, 'contact_name')),
                'contact_email' => Arr::get($metadata, 'contact_email', Arr::get($payload, 'contact_email')),
                'workspace_name' => Arr::get($metadata, 'workspace_name', Arr::get($payload, 'workspace_name')),
                'workspace_id' => Arr::get($metadata, 'workspace_id', Arr::get($payload, 'workspace_id')),
                'tenant_id' => $source === 'tenant' ? Arr::get($metadata, 'workspace_id', Arr::get($payload, 'workspace_id')) : null,
                'page_path' => Arr::get($metadata, 'page_path', Arr::get($payload, 'page_path')),
                'app_version' => Arr::get($metadata, 'app_version', Arr::get($payload, 'app_version')),
                'user_role' => Arr::get($metadata, 'user_role', Arr::get($payload, 'user_role')),
                'tenant_domain' => Arr::get($metadata, 'tenant_domain'),
                'request_ip' => Arr::get($metadata, 'request_ip'),
                'user_agent' => Arr::get($metadata, 'user_agent'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
