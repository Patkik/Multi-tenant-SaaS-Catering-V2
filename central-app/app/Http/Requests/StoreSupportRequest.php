<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['feedback', 'bug'])],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'workspace_id' => ['nullable', 'string', 'max:255'],
            'page_path' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'user_role' => ['nullable', 'string', 'max:120'],
        ];
    }
}
