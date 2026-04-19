<?php

namespace App\Http\Requests;

use App\Support\TenantRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCentralTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['sometimes', 'required', 'string', 'max:50'],
            'firstname' => ['sometimes', 'required', 'string', 'max:80'],
            'lastname' => ['sometimes', 'required', 'string', 'max:80'],
            'mi' => ['sometimes', 'nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['sometimes', 'required', Rule::in(TenantRoles::all())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
