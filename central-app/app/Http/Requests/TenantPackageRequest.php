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
            'menu_items' => ['sometimes', 'array', 'max:100'],
            'menu_items.*.name' => ['required_with:menu_items', 'string', 'max:120'],
            'menu_items.*.category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'menu_items.*.servings' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'menu_items.*.unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'menu_items.*.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'menu_published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
