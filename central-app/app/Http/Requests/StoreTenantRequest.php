<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', Rule::unique('tenants', 'domain')],
            'database_name' => [
                'required',
                'string',
                'max:64',
                'regex:/\A[a-zA-Z0-9_]+\z/',
                Rule::unique('tenants', 'database_name'),
            ],
            'plan_code' => ['nullable', 'string', 'max:50'],
            'plan_entitlements' => ['nullable', 'array'],
            'plan_entitlements.*' => ['string', 'max:50'],
        ];
    }
}
