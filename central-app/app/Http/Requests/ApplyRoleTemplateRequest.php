<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRoleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->attributes->get('central_admin_authenticated', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'strategy' => ['required', 'string', 'in:merge,replace'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requested_by_admin' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
