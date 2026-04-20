<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'string', 'timezone'],
            'date_format' => ['sometimes', 'string', 'in:MMMM DD, YYYY,DD MMM YYYY,YYYY-MM-DD'],
            'default_guest_capacity' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'reminder_schedule' => ['sometimes', 'string', 'in:24_hours,48_hours,72_hours'],
            'auto_invoice_after_event' => ['sometimes', 'boolean'],
            'two_factor_required_for_admin' => ['sometimes', 'boolean'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
