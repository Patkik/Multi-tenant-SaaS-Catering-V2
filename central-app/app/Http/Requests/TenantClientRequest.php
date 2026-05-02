<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id;
        $requiredRule = $this->isMethod('post') ? ['required'] : ['sometimes'];

        return [
            'first_name' => [...$requiredRule, 'string', 'max:80'],
            'last_name' => [...$requiredRule, 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('clients', 'email')->ignore($clientId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
