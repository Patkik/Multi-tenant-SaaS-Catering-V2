<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantAssignmentRequest;
use App\Models\EventStaff;
use App\Models\TenantEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TenantAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EventStaff::query()->with(['event.client', 'staff']);

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->integer('event_id'));
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', (int) $request->integer('staff_id'));
        }

        return response()->json([
            'data' => $query
                ->latest('id')
                ->paginate(20)
                ->through(function (EventStaff $assignment): array {
                    $shiftStart = $assignment->shift_start_at ?? $assignment->start_time;
                    $shiftEnd = $assignment->shift_end_at ?? $assignment->end_time;

                    return [
                        'id' => $assignment->id,
                        'assignment_role' => $assignment->assignment_role,
                        'shift_start_at' => optional($shiftStart)?->toIso8601String(),
                        'shift_end_at' => optional($shiftEnd)?->toIso8601String(),
                        'start_time' => optional($shiftStart)?->toIso8601String(),
                        'end_time' => optional($shiftEnd)?->toIso8601String(),
                        'event' => [
                            'id' => $assignment->event?->id,
                            'event_name' => $assignment->event?->event_name,
                            'event_date' => optional($assignment->event?->event_date)?->toDateString(),
                            'location' => $assignment->event?->location,
                            'client_name' => trim(($assignment->event?->client?->first_name ?? '').' '.($assignment->event?->client?->last_name ?? '')),
                        ],
                        'staff' => [
                            'id' => $assignment->staff?->id,
                            'full_name' => trim(($assignment->staff?->first_name ?? '').' '.($assignment->staff?->last_name ?? '')),
                            'position' => $assignment->staff?->position,
                        ],
                    ];
                }),
        ]);
    }

    public function store(TenantAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $event = TenantEvent::query()->findOrFail((int) $validated['event_id']);
        [$startAt, $endAt] = $this->resolveWindow($event, $validated);

        if ($this->hasConflict((int) $validated['staff_id'], $event->id, $event, $startAt, $endAt)) {
            return response()->json([
                'message' => 'Staff member has a conflicting assignment for this timeslot.',
            ], 422);
        }

        $assignment = EventStaff::query()->updateOrCreate(
            [
                'event_id' => (int) $validated['event_id'],
                'staff_id' => (int) $validated['staff_id'],
            ],
            [
                'assignment_role' => $validated['assignment_role'] ?? null,
                'shift_start_at' => $startAt,
                'shift_end_at' => $endAt,
                'start_time' => $startAt,
                'end_time' => $endAt,
            ],
        );

        $assignment->load(['event.client', 'staff']);
        $shiftStart = $assignment->shift_start_at ?? $assignment->start_time;
        $shiftEnd = $assignment->shift_end_at ?? $assignment->end_time;

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'assignment_role' => $assignment->assignment_role,
                'shift_start_at' => optional($shiftStart)?->toIso8601String(),
                'shift_end_at' => optional($shiftEnd)?->toIso8601String(),
                'start_time' => optional($shiftStart)?->toIso8601String(),
                'end_time' => optional($shiftEnd)?->toIso8601String(),
                'event' => [
                    'id' => $assignment->event?->id,
                    'event_name' => $assignment->event?->event_name,
                    'event_date' => optional($assignment->event?->event_date)?->toDateString(),
                ],
                'staff' => [
                    'id' => $assignment->staff?->id,
                    'full_name' => trim(($assignment->staff?->first_name ?? '').' '.($assignment->staff?->last_name ?? '')),
                ],
            ],
        ], 201);
    }

    public function destroy(EventStaff $assignment): JsonResponse
    {
        $assignment->delete();

        return response()->json([
            'data' => [
                'message' => 'Staff assignment removed successfully.',
            ],
        ]);
    }

    private function resolveWindow(TenantEvent $event, array $validated): array
    {
        $startAt = isset($validated['shift_start_at'])
            ? Carbon::parse((string) $validated['shift_start_at'])
            : (isset($validated['start_time'])
                ? Carbon::parse((string) $validated['start_time'])
                : ($event->start_time ? Carbon::parse((string) $event->start_time) : Carbon::parse((string) $event->event_date)->startOfDay()->addHours(8)));

        $endAt = isset($validated['shift_end_at'])
            ? Carbon::parse((string) $validated['shift_end_at'])
            : (isset($validated['end_time'])
                ? Carbon::parse((string) $validated['end_time'])
                : ($event->end_time ? Carbon::parse((string) $event->end_time) : $startAt->copy()->addHours(8)));

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $startAt->copy()->addHours(4);
        }

        return [$startAt, $endAt];
    }

    private function hasConflict(int $staffId, int $eventId, TenantEvent $event, Carbon $startAt, Carbon $endAt): bool
    {
        return EventStaff::query()
            ->where('staff_id', $staffId)
            ->where('event_id', '!=', $eventId)
            ->where(function ($query) use ($event, $startAt, $endAt): void {
                $query
                    ->where(function ($timeRangeQuery) use ($startAt, $endAt): void {
                        $timeRangeQuery
                            ->whereRaw('COALESCE(shift_start_at, start_time) IS NOT NULL')
                            ->whereRaw('COALESCE(shift_end_at, end_time) IS NOT NULL')
                            ->where(function ($overlapQuery) use ($startAt, $endAt): void {
                                $overlapQuery
                                    ->whereRaw('COALESCE(shift_start_at, start_time) BETWEEN ? AND ?', [$startAt, $endAt])
                                    ->orWhereRaw('COALESCE(shift_end_at, end_time) BETWEEN ? AND ?', [$startAt, $endAt])
                                    ->orWhere(function ($containQuery) use ($startAt, $endAt): void {
                                        $containQuery
                                            ->whereRaw('COALESCE(shift_start_at, start_time) <= ?', [$startAt])
                                            ->whereRaw('COALESCE(shift_end_at, end_time) >= ?', [$endAt]);
                                    });
                            });
                    })
                    ->orWhere(function ($legacyDayQuery) use ($event): void {
                        $legacyDayQuery
                            ->whereNull('shift_start_at')
                            ->whereNull('shift_end_at')
                            ->whereNull('start_time')
                            ->whereNull('end_time')
                            ->whereHas('event', function ($eventQuery) use ($event): void {
                                $eventQuery->whereDate('event_date', optional($event->event_date)->toDateString());
                            });
                    });
            })
            ->exists();
    }
}
