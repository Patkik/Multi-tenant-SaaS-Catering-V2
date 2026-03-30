<?php

namespace App\Http\Requests;

class ApplyRoleTemplateToTenantAliasRequest extends ApplyRoleTemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
        ]);
    }
}
