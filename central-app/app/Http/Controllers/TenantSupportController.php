<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequest;
use App\Services\SupportMessageService;
use Illuminate\Http\JsonResponse;

class TenantSupportController extends Controller
{
    public function __construct(
        private readonly SupportMessageService $supportMessageService,
    ) {
    }

    public function store(StoreSupportRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = tenant();
        $validated = $request->validated();

        $workspaceName = (string) ($tenant?->company_name ?? $validated['workspace_name'] ?? 'Tenant Workspace');
        $workspaceId = (string) ($tenant?->getTenantKey() ?? $validated['workspace_id'] ?? 'tenant');

        $this->supportMessageService->send('tenant', $validated, [
            'app_label' => 'Tenant Workspace',
            'app_version' => config('app.version'),
            'workspace_name' => $workspaceName,
            'workspace_id' => $workspaceId,
            'tenant_domain' => $tenant?->domains()->first()?->domain,
            'contact_name' => $validated['contact_name'] ?? $user?->display_name ?? $user?->name,
            'contact_email' => $validated['contact_email'] ?? $user?->email,
            'user_role' => $validated['user_role'] ?? $user?->role ?? null,
            'page_path' => $validated['page_path'] ?? $request->path(),
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'message' => 'Your support request has been sent to the tenant support inbox.',
            ],
        ]);
    }
}
