<?php

namespace App\Http\Requests;

use App\Support\PlanFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCentralTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:120'],
            'subdomain' => ['sometimes', 'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'plan' => ['sometimes', 'required', 'string', Rule::in(PlanFeatures::planKeys())],
            'enabled_features' => ['sometimes', 'required', 'array'],
            'enabled_features.*' => ['required', 'string', Rule::in($this->featureKeys())],
            'client_access' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function featureKeys(): array
    {
        return [
            PlanFeatures::EVENT_MANAGEMENT,
            PlanFeatures::CLIENT_PORTAL,
            PlanFeatures::STAFF_ASSIGNMENT,
            PlanFeatures::ADVANCED_ANALYTICS,
            PlanFeatures::BRANDING_CONTROLS,
        ];
    }
}
