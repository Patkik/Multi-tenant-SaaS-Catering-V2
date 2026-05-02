<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantEventRequest;
use App\Http\Requests\UpdateTenantEventStatusRequest;
use App\Models\Client;
use App\Models\TenantEvent;
use App\Services\EventQuotaService;
use App\Support\PlanFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! tenant()) {
            return response()->json([
                'message' => 'Tenant context is required for this endpoint.',
            ], 400);
        }

        $query = TenantEvent::query()->with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('month')) {
            $month = Carbon::parse($request->string('month')->toString());
            $query->whereBetween('event_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);
        }

        return response()->json([
            'data' => $query
                ->latest('event_date')
                ->paginate(20)
                ->through(fn (TenantEvent $event) => [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => optional($event->event_date)->toDateString(),
                    'location' => $event->location,
                    'guest_count' => $event->guest_count,
                    'status' => $event->status,
                    'quoted_total' => $event->quoted_total,
                    'client' => [
                        'id' => $event->client?->id,
                        'full_name' => trim(($event->client?->first_name ?? '').' '.($event->client?->last_name ?? '')),
                        'email' => $event->client?->email,
                    ],
                ]),
        ]);
    }

    public function store(StoreTenantEventRequest $request, EventQuotaService $eventQuotaService): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required for this endpoint.',
            ], 400);
        }

        $plan = (string) ($tenant->getAttribute('plan') ?? 'free');
        $eventDate = Carbon::parse((string) $request->validated('event_date'));
        $monthlyLimit = PlanFeatures::monthlyActiveEventLimit($plan);
        $quotaErrorMessage = 'Free plan monthly active event limit reached.';

        try {
            $event = DB::transaction(function () use ($eventQuotaService, $plan, $eventDate, $request, $quotaErrorMessage): TenantEvent {
                if (! $eventQuotaService->canCreateEventForCurrentMonth($plan, $eventDate, true)) {
                    throw ValidationException::withMessages([
                        'event_date' => [$quotaErrorMessage],
                    ]);
                }

                $clientId = $request->validated('client_id');

                if (! $clientId) {
                    $client = Client::query()->create($request->validated('client'));
                    $clientId = $client->id;
                }

                return TenantEvent::query()->create([
                    'client_id' => $clientId,
                    'catering_package_id' => $request->validated('catering_package_id'),
                    'event_name' => $request->validated('event_name'),
                    'event_date' => $request->validated('event_date'),
                    'start_time' => $request->validated('start_time'),
                    'end_time' => $request->validated('end_time'),
                    'location' => $request->validated('location'),
                    'guest_count' => $request->validated('guest_count'),
                    'status' => $request->validated('status', 'pending'),
                    'quoted_total' => $request->validated('quoted_total'),
                    'notes' => $request->validated('notes'),
                ]);
            }, 3);
        } catch (ValidationException) {
            return response()->json([
                'message' => $quotaErrorMessage,
                'plan' => $plan,
                'max_active_events' => $monthlyLimit,
            ], 422);
        }

        $event->load('client');

        return response()->json([
            'message' => 'Event created successfully.',
            'data' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => optional($event->event_date)->toDateString(),
                'location' => $event->location,
                'guest_count' => $event->guest_count,
                'status' => $event->status,
                'plan' => $plan,
                'client' => [
                    'id' => $event->client?->id,
                    'first_name' => $event->client?->first_name,
                    'last_name' => $event->client?->last_name,
                    'email' => $event->client?->email,
                ],
            ],
        ], 201);
    }

    public function updateStatus(UpdateTenantEventStatusRequest $request, TenantEvent $event): JsonResponse
    {
        if (! tenant()) {
            return response()->json([
                'message' => 'Tenant context is required for this endpoint.',
            ], 400);
        }

        $event->status = (string) $request->validated('status');
        $event->save();
        $event->load('client');

        return response()->json([
            'data' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => optional($event->event_date)->toDateString(),
                'location' => $event->location,
                'guest_count' => $event->guest_count,
                'status' => $event->status,
                'quoted_total' => $event->quoted_total,
                'client' => [
                    'id' => $event->client?->id,
                    'full_name' => trim(($event->client?->first_name ?? '').' '.($event->client?->last_name ?? '')),
                    'email' => $event->client?->email,
                ],
            ],
        ]);
    }
}
