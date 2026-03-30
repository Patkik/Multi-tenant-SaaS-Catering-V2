<?php

namespace App\Http\Requests;

use App\Models\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Feature $feature */
        $feature = $this->route('feature');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('features', 'name')->ignore($feature->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'string', 'in:Core,CRM,Billing,Reporting,Integration,Admin'],
            'default_enabled' => ['sometimes', 'boolean'],
            'requires_plan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'deprecated_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
