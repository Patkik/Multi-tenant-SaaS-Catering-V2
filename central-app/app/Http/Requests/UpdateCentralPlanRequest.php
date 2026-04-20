<?php

namespace App\Http\Requests;

use App\Support\PlanFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCentralPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_price' => ['required', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:1'],
            'monthly_active_event_limit' => ['nullable', 'integer', 'min:1'],
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['string', Rule::in(PlanFeatures::allFeatures())],
        ];
    }
}
