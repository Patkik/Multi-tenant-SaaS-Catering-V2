<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'assignment_role' => ['sometimes', 'nullable', 'string', 'max:80'],
            'shift_start_at' => ['sometimes', 'nullable', 'date'],
            'shift_end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:shift_start_at'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_time'],
        ];
    }
}
