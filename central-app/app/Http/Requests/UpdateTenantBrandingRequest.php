<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'primary_color' => ['sometimes', 'nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
