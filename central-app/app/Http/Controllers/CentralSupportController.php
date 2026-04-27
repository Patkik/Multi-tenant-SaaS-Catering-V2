<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequest;
use App\Services\SupportMessageService;
use Illuminate\Http\JsonResponse;

class CentralSupportController extends Controller
{
    public function __construct(
        private readonly SupportMessageService $supportMessageService,
    ) {
    }

    public function store(StoreSupportRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $this->supportMessageService->send('central', $validated, [
            'app_label' => 'Central Platform',
            'app_version' => config('app.version'),
            'workspace_name' => 'Central Platform',
            'workspace_id' => 'central',
            'contact_name' => $validated['contact_name'] ?? $user?->name,
            'contact_email' => $validated['contact_email'] ?? $user?->email,
            'user_role' => $validated['user_role'] ?? implode(', ', $user?->getRoleNames()->values()->all() ?? []),
            'page_path' => $validated['page_path'] ?? $request->path(),
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'message' => 'Your support request has been sent to the central team.',
            ],
        ]);
    }
}
