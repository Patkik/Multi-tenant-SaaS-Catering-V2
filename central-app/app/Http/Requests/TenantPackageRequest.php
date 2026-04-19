<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requiredRule = $this->isMethod('post') ? ['required'] : ['sometimes'];

        return [
            'name' => [...$requiredRule, 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'pricing_mode' => ['sometimes', 'in:flat,per_person'],
            'base_price' => [...$requiredRule, 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
