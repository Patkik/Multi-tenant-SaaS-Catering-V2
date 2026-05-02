<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_name' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled'],
            'quoted_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'catering_package_id' => ['nullable', 'integer', 'exists:catering_packages,id'],
            'client' => ['required_without:client_id', 'array'],
            'client.first_name' => ['required_with:client', 'string', 'max:80'],
            'client.last_name' => ['required_with:client', 'string', 'max:80'],
            'client.email' => ['nullable', 'email', 'max:150'],
            'client.phone' => ['nullable', 'string', 'max:30'],
            'client.address' => ['nullable', 'string', 'max:255'],
            'client.notes' => ['nullable', 'string'],
        ];
    }
}
