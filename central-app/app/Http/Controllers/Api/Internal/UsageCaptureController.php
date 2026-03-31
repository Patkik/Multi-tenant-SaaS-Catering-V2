<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CaptureUsageSnapshotRequest;
use App\Models\UsageSnapshot;
use Illuminate\Http\JsonResponse;

class UsageCaptureController extends Controller
{
    public function __invoke(CaptureUsageSnapshotRequest $request): JsonResponse
    {
        $payload = $request->validated();

        /** @var UsageSnapshot $snapshot */
        $snapshot = UsageSnapshot::query()->updateOrCreate(
            [
                'tenant_id' => $payload['tenant_id'],
                'window_type' => $payload['window_type'],
                'captured_at' => $payload['captured_at'],
            ],
            [
                'users_total' => $payload['users_total'],
                'storage_mb' => $payload['storage_mb'],
                'orders_count' => $payload['orders_count'],
                'metadata' => $payload['metadata'] ?? null,
            ],
        );

        return response()->json([
            'data' => $snapshot,
        ], 202);
    }
}
