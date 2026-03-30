<?php

namespace App\Http\Requests;

use App\Models\RoleTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleTemplateRequest extends FormRequest
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
        /** @var RoleTemplate $roleTemplate */
        $roleTemplate = $this->route('roleTemplate');

        return [
            'role_name' => ['sometimes', 'string', 'max:100', Rule::unique('role_templates', 'name')->ignore($roleTemplate->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_system_default' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:255', 'distinct', 'regex:/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/'],
            'feature_keys' => ['sometimes', 'array'],
            'feature_keys.*' => ['string', 'max:255', 'distinct', 'exists:features,name'],
        ];
    }
}
