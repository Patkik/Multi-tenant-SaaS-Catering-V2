<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleTemplateRequest;
use App\Http\Requests\UpdateRoleTemplateRequest;
use App\Models\RoleTemplate;
use App\Services\RoleTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
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

    public function permissions(RoleTemplate $roleTemplate): JsonResponse
    {
        Gate::authorize('admin.role-templates.read');

        $roleTemplate->loadMissing(['permissions', 'features']);

        /** @var Collection<string, list<string>> $permissionsByRole */
        $permissionsByRole = $roleTemplate->permissions
            ->groupBy('role_name')
            ->map(static fn (Collection $items): array => $items->pluck('permission')->values()->all());

        /** @var Collection<string, list<array{feature_key:string,is_enabled:bool}>> $featuresByRole */
        $featuresByRole = $roleTemplate->features
            ->groupBy('role_name')
            ->map(static fn (Collection $items): array => $items
                ->map(static fn ($feature): array => [
                    'feature_key' => (string) $feature->feature_key,
                    'is_enabled' => (bool) $feature->is_enabled,
                ])
                ->values()
                ->all());

        /** @var Collection<int, string> $roleNames */
        $roleNames = $permissionsByRole->keys()
            ->merge($featuresByRole->keys())
            ->unique()
            ->values();

        $grouped = $roleNames
            ->map(static fn (string $roleName): array => [
                'role_name' => $roleName,
                'permissions' => $permissionsByRole->get($roleName, []),
                'features' => $featuresByRole->get($roleName, []),
            ])
            ->all();

        return response()->json([
            'data' => [
                'role_template_id' => (string) $roleTemplate->id,
                'grouped' => $grouped,
            ],
        ]);
    }
}
