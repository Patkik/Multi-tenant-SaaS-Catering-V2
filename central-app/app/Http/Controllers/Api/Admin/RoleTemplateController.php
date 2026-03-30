<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleTemplateRequest;
use App\Http\Requests\UpdateRoleTemplateRequest;
use App\Models\RoleTemplate;
use App\Services\RoleTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleTemplateController extends Controller
{
    public function __construct(private readonly RoleTemplateService $roleTemplateService)
    {
    }

    public function index(): JsonResponse
    {
        Gate::authorize('admin.role-templates.read');

        return response()->json([
            'data' => RoleTemplate::query()
                ->with(['permissions', 'features'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRoleTemplateRequest $request): JsonResponse
    {
        Gate::authorize('admin.role-templates.write');

        $roleTemplate = $this->roleTemplateService->create($request->validated());

        return response()->json([
            'data' => $roleTemplate,
        ], 201);
    }

    public function update(UpdateRoleTemplateRequest $request, RoleTemplate $roleTemplate): JsonResponse
    {
        Gate::authorize('admin.role-templates.write');

        $updatedTemplate = $this->roleTemplateService->update($roleTemplate, $request->validated());

        return response()->json([
            'data' => $updatedTemplate,
        ]);
    }
}
