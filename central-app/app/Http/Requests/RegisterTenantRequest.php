<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'subdomain' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'plan' => ['required', 'string', 'in:free,starter,business,enterprise'],
            'client_access' => ['sometimes', 'boolean'],
            'admin' => ['required', 'array'],
            'admin.username' => ['required', 'string', 'max:50'],
            'admin.lastname' => ['required', 'string', 'max:50'],
            'admin.mi' => ['nullable', 'string', 'max:10'],
            'admin.firstname' => ['required', 'string', 'max:50'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
