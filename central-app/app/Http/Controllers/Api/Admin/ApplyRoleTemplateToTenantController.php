<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoleTemplate;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApplyRoleTemplateToTenantController extends Controller
{
    public function __invoke(Tenant $tenant, RoleTemplate $roleTemplate): JsonResponse
    {
        Gate::authorize('admin.role-templates.apply');

        return response()->json([
            'status' => 'not_implemented',
            'message' => 'Role template apply is not implemented yet for this tenant.',
            'tenant_id' => $tenant->id,
            'role_template_id' => $roleTemplate->id,
        ], 501);
    }
}
