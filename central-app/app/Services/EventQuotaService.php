<?php

namespace App\Services;

use App\Support\PlanFeatures;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EventQuotaService
{
    public function canCreateEventForCurrentMonth(string $plan, CarbonInterface $date, bool $lockRowsForUpdate = false): bool
    {
        $monthlyLimit = PlanFeatures::monthlyActiveEventLimit($plan);

        if ($monthlyLimit === null) {
            return true;
        }

        $activeStatuses = ['pending', 'confirmed', 'completed'];

        $query = DB::table('events')
            ->whereIn('status', $activeStatuses)
            ->whereBetween('event_date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ]);

        if ($lockRowsForUpdate) {
            $query->lockForUpdate();
        }

        $count = $query->count();

        return $count < $monthlyLimit;
    }
}
