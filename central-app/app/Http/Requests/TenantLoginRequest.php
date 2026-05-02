<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }
}
