<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyRoleTemplateRequest;
use App\Models\RoleTemplate;
use App\Models\Tenant;
use App\Services\RoleTemplateApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApplyRoleTemplateToTenantController extends Controller
{
    public function __construct(private readonly RoleTemplateApplicationService $roleTemplateApplicationService)
    {
    }

    public function __invoke(ApplyRoleTemplateRequest $request, Tenant $tenant, RoleTemplate $roleTemplate): JsonResponse
    {
        Gate::authorize('admin.role-templates.apply');

        $application = $this->roleTemplateApplicationService->queueApplication(
            $tenant,
            $roleTemplate,
            $request->validated(),
        );

        return response()->json([
            'data' => $application,
        ], 202);
    }
}
