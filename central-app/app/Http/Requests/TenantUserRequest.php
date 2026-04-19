<?php

namespace App\Http\Requests;

use App\Support\TenantRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;
        $requiredRule = $this->isMethod('post') ? ['required'] : ['sometimes'];

        return [
            'username' => [...$requiredRule, 'string', 'max:50', Rule::unique('users', 'username')->ignore($memberId)],
            'firstname' => [...$requiredRule, 'string', 'max:80'],
            'lastname' => [...$requiredRule, 'string', 'max:80'],
            'mi' => ['sometimes', 'nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($memberId)],
            'password' => $this->isMethod('post')
                ? ['required', 'string', 'min:8', 'max:255', 'confirmed']
                : ['sometimes', 'nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'role' => [...$requiredRule, Rule::in(TenantRoles::all())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
