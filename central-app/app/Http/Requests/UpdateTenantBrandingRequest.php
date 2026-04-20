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
            'logo_file' => ['sometimes', 'file', 'mimes:png', 'mimetypes:image/png', 'dimensions:max_width=600,max_height=600'],
            'heading_font' => ['sometimes', 'nullable', 'string', 'max:80'],
            'body_font' => ['sometimes', 'nullable', 'string', 'max:80'],
            'layout_density' => ['sometimes', 'nullable', 'in:comfortable,compact,airy'],
            'card_radius' => ['sometimes', 'nullable', 'integer', 'min:8', 'max:28'],
            'hero_message' => ['sometimes', 'nullable', 'string', 'max:280'],
            'homepage_sections' => ['sometimes', 'array', 'max:20'],
            'homepage_sections.*.id' => ['required_with:homepage_sections', 'string', 'max:80'],
            'homepage_sections.*.label' => ['required_with:homepage_sections', 'string', 'max:120'],
            'homepage_sections.*.enabled' => ['required_with:homepage_sections', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo_file.mimes' => 'Logo must be a PNG file.',
            'logo_file.mimetypes' => 'Logo must be a PNG file.',
            'logo_file.dimensions' => 'Logo dimensions must be 600x600 pixels or smaller.',
        ];
    }
}
