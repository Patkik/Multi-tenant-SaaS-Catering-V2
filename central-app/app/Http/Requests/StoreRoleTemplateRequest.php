<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleTemplateRequest extends FormRequest
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
            'role_name' => ['required', 'string', 'max:100', 'unique:role_templates,name'],
            'description' => ['nullable', 'string'],
            'is_system_default' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'max:255', 'distinct', 'regex:/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/'],
            'feature_keys' => ['required', 'array'],
            'feature_keys.*' => ['string', 'max:255', 'distinct', 'exists:features,name'],
        ];
    }
}
