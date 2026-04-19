<?php

namespace App\Http\Requests;

use App\Support\PlanFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(PlanFeatures::planKeys())],
        ];
    }
}
