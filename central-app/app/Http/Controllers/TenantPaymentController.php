<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantPaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with(['event.client']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->string('payment_type')->toString());
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->integer('event_id'));
        }

        return response()->json([
            'data' => $query
                ->latest('id')
                ->paginate(15)
                ->through(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'event_id' => $payment->event_id,
                    'amount' => $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'reference' => $payment->reference,
                    'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                    'event' => [
                        'id' => $payment->event?->id,
                        'event_name' => $payment->event?->event_name,
                        'event_date' => optional($payment->event?->event_date)?->toDateString(),
                        'quoted_total' => $payment->event?->quoted_total,
                        'client_name' => trim(($payment->event?->client?->first_name ?? '').' '.($payment->event?->client?->last_name ?? '')),
                    ],
                ]),
            'meta' => [
                'total_paid' => (float) Payment::query()->where('status', 'paid')->sum('amount'),
                'pending_collection' => (float) Payment::query()->where('status', 'pending')->sum('amount'),
            ],
        ]);
    }

    public function store(TenantPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['status'] ?? null) === 'paid' && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $payment = Payment::query()->create($validated);
        $payment->load(['event.client']);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'event_id' => $payment->event_id,
                'amount' => $payment->amount,
                'payment_type' => $payment->payment_type,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                'event' => [
                    'id' => $payment->event?->id,
                    'event_name' => $payment->event?->event_name,
                    'event_date' => optional($payment->event?->event_date)?->toDateString(),
                    'client_name' => trim(($payment->event?->client?->first_name ?? '').' '.($payment->event?->client?->last_name ?? '')),
                ],
            ],
        ], 201);
    }

    public function update(TenantPaymentRequest $request, Payment $payment): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['status'] ?? null) === 'paid' && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $payment->fill($validated);
        $payment->save();
        $payment->load(['event.client']);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'event_id' => $payment->event_id,
                'amount' => $payment->amount,
                'payment_type' => $payment->payment_type,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                'event' => [
                    'id' => $payment->event?->id,
                    'event_name' => $payment->event?->event_name,
                    'event_date' => optional($payment->event?->event_date)?->toDateString(),
                    'client_name' => trim(($payment->event?->client?->first_name ?? '').' '.($payment->event?->client?->last_name ?? '')),
                ],
            ],
        ]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return response()->json([
            'data' => [
                'message' => 'Payment deleted successfully.',
            ],
        ]);
    }
}
