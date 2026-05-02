<?php

namespace App\Http\Requests;

use App\Support\TenantRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:80'],
            'lastname' => ['required', 'string', 'max:80'],
            'mi' => ['sometimes', 'nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role' => ['required', Rule::in(TenantRoles::all())],
            'username' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'username')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
