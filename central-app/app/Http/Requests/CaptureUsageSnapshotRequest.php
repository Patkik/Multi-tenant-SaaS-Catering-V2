<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptureUsageSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'window_type' => ['required', 'string', 'in:hourly,daily'],
            'captured_at' => ['required', 'date'],
            'users_total' => ['required', 'integer', 'min:0'],
            'storage_mb' => ['required', 'numeric', 'min:0'],
            'orders_count' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
