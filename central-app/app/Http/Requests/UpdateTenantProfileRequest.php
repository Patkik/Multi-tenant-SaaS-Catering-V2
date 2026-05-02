<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'firstname' => ['sometimes', 'nullable', 'string', 'max:80'],
            'lastname' => ['sometimes', 'nullable', 'string', 'max:80'],
            'mi' => ['sometimes', 'nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'avatar_file' => [
                'sometimes',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:2048',
                'dimensions:max_width=1200,max_height=1200',
            ],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar_file.mimes' => 'Avatar must be a PNG, JPG, JPEG, or WEBP file.',
            'avatar_file.mimetypes' => 'Avatar must be a PNG, JPG, JPEG, or WEBP file.',
            'avatar_file.max' => 'Avatar must be 2MB or smaller.',
            'avatar_file.dimensions' => 'Avatar dimensions must be 1200x1200 pixels or smaller.',
        ];
    }
}
