<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff')?->id;
        $requiredRule = $this->isMethod('post') ? ['required'] : ['sometimes'];

        return [
            'first_name' => [...$requiredRule, 'string', 'max:80'],
            'last_name' => [...$requiredRule, 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('staff', 'email')->ignore($staffId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'position' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
