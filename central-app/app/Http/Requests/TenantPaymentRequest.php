<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requiredRule = $this->isMethod('post') ? ['required'] : ['sometimes'];

        return [
            'event_id' => [...$requiredRule, 'integer', 'exists:events,id'],
            'amount' => [...$requiredRule, 'numeric', 'min:0'],
            'payment_type' => [...$requiredRule, 'in:downpayment,balance,full'],
            'status' => ['sometimes', 'in:pending,paid,failed,refunded'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
