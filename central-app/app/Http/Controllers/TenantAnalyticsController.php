<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use App\Models\TenantEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TenantAnalyticsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totalEvents = TenantEvent::query()->count();
        $confirmedEvents = TenantEvent::query()->where('status', 'confirmed')->count();
        $completedEvents = TenantEvent::query()->where('status', 'completed')->count();
        $cancelledEvents = TenantEvent::query()->where('status', 'cancelled')->count();

        $driver = DB::connection()->getDriverName();
        $monthExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', event_date)",
            'pgsql' => "TO_CHAR(event_date, 'YYYY-MM')",
            default => "DATE_FORMAT(event_date, '%Y-%m')",
        };

        $totalRevenue = (float) Payment::query()->where('status', 'paid')->sum('amount');
        $pendingCollections = (float) Payment::query()->where('status', 'pending')->sum('amount');
        $quotedTotal = (float) TenantEvent::query()->sum('quoted_total');

        $monthlyEvents = TenantEvent::query()
            ->selectRaw("{$monthExpression} as month, COUNT(*) as total")
            ->where('event_date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $topClients = Client::query()
            ->withCount('events')
            ->orderByDesc('events_count')
            ->limit(5)
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->full_name,
                'events_count' => (int) $client->events_count,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'kpis' => [
                    'total_events' => $totalEvents,
                    'confirmed_events' => $confirmedEvents,
                    'completed_events' => $completedEvents,
                    'cancelled_events' => $cancelledEvents,
                    'total_revenue' => $totalRevenue,
                    'pending_collections' => $pendingCollections,
                    'projected_balance_due' => max($quotedTotal - $totalRevenue, 0),
                ],
                'series' => [
                    'monthly_events' => $monthlyEvents,
                ],
                'top_clients' => $topClients,
            ],
        ]);
    }
}
