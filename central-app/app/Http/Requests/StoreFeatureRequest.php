<?php

namespace App\Http\Requests;

use App\Support\FeatureCategories;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeatureRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:features,name'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:'.implode(',', FeatureCategories::all())],
            'default_enabled' => ['required', 'boolean'],
            'requires_plan' => ['nullable', 'string', 'max:50'],
        ];
    }
}
