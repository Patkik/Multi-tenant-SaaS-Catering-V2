<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeatureRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', 'unique:features,name'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:Core,CRM,Billing,Reporting,Integration,Admin'],
            'default_enabled' => ['required', 'boolean'],
            'requires_plan' => ['nullable', 'string', 'max:50'],
        ];
    }
}
