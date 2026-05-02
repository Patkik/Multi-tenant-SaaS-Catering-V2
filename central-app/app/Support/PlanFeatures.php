<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlanFeatures
{
    private const OVERRIDE_TABLE = 'central_plan_overrides';

    private const PLAN_DEFINITIONS = [
        'free' => [
            'label' => 'Free',
            'monthly_price' => 0,
            'user_limit' => 3,
            'monthly_active_event_limit' => 10,
            'features' => [
                self::EVENT_MANAGEMENT,
            ],
        ],
        'starter' => [
            'label' => 'Starter',
            'monthly_price' => 699,
            'user_limit' => 10,
            'monthly_active_event_limit' => null,
            'features' => [
                self::EVENT_MANAGEMENT,
                self::CLIENT_PORTAL,
                self::STAFF_ASSIGNMENT,
            ],
        ],
        'business' => [
            'label' => 'Business',
            'monthly_price' => 1299,
            'user_limit' => 25,
            'monthly_active_event_limit' => null,
            'features' => [
                self::EVENT_MANAGEMENT,
                self::CLIENT_PORTAL,
                self::STAFF_ASSIGNMENT,
                self::ADVANCED_ANALYTICS,
                self::BRANDING_CONTROLS,
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'monthly_price' => 2499,
            'user_limit' => null,
            'monthly_active_event_limit' => null,
            'features' => [
                self::EVENT_MANAGEMENT,
                self::CLIENT_PORTAL,
                self::STAFF_ASSIGNMENT,
                self::ADVANCED_ANALYTICS,
                self::BRANDING_CONTROLS,
            ],
        ],
    ];

    public const EVENT_MANAGEMENT = 'event_management';

    public const CLIENT_PORTAL = 'client_portal';

    public const STAFF_ASSIGNMENT = 'staff_assignment';

    public const ADVANCED_ANALYTICS = 'advanced_analytics';

    public const BRANDING_CONTROLS = 'branding_controls';

    private static ?array $plansCache = null;

    public static function forPlan(string $plan): array
    {
        $normalizedPlan = self::normalizePlan($plan);

        return self::PLAN_DEFINITIONS[$normalizedPlan]['features'];
    }

    public static function plans(): array
    {
        if (self::$plansCache !== null) {
            return self::$plansCache;
        }

        $plans = self::PLAN_DEFINITIONS;
        $overrides = self::planOverrides();

        foreach ($overrides as $planKey => $override) {
            if (! array_key_exists($planKey, $plans)) {
                continue;
            }

            $plans[$planKey]['monthly_price'] = (int) Arr::get($override, 'monthly_price', $plans[$planKey]['monthly_price']);
            $plans[$planKey]['user_limit'] = Arr::get($override, 'user_limit', $plans[$planKey]['user_limit']);
            $plans[$planKey]['monthly_active_event_limit'] = Arr::get(
                $override,
                'monthly_active_event_limit',
                $plans[$planKey]['monthly_active_event_limit'],
            );

            $features = Arr::get($override, 'features');
            if (is_array($features) && $features !== []) {
                $plans[$planKey]['features'] = collect($features)
                    ->map(fn ($feature) => (string) $feature)
                    ->filter(fn (string $feature) => in_array($feature, self::allFeatures(), true))
                    ->values()
                    ->all();
            }
        }

        self::$plansCache = $plans;

        return self::$plansCache;
    }

    public static function planKeys(): array
    {
        return array_keys(self::PLAN_DEFINITIONS);
    }

    public static function normalizePlan(string $plan): string
    {
        return array_key_exists($plan, self::PLAN_DEFINITIONS) ? $plan : 'free';
    }

    public static function detailsForPlan(string $plan): array
    {
        $normalizedPlan = self::normalizePlan($plan);

        return self::PLAN_DEFINITIONS[$normalizedPlan];
    }

    public static function monthlyActiveEventLimit(string $plan): ?int
    {
        return self::detailsForPlan($plan)['monthly_active_event_limit'];
    }

    public static function hasFeature(string $plan, string $feature): bool
    {
        return in_array($feature, self::forPlan($plan), true);
    }

    public static function supportsClientPortal(string $plan): bool
    {
        return self::hasFeature($plan, self::CLIENT_PORTAL);
    }

    public static function clearCache(): void
    {
        self::$plansCache = null;
    }

    public static function allFeatures(): array
    {
        return [
            self::EVENT_MANAGEMENT,
            self::CLIENT_PORTAL,
            self::STAFF_ASSIGNMENT,
            self::ADVANCED_ANALYTICS,
            self::BRANDING_CONTROLS,
        ];
    }

    private static function planOverrides(): array
    {
        try {
            $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

            return DB::connection($centralConnection)
                ->table(self::OVERRIDE_TABLE)
                ->get()
                ->mapWithKeys(function ($record) {
                    return [
                        (string) $record->plan_key => [
                            'monthly_price' => $record->monthly_price,
                            'user_limit' => $record->user_limit,
                            'monthly_active_event_limit' => $record->monthly_active_event_limit,
                            'features' => is_string($record->features)
                                ? json_decode($record->features, true)
                                : $record->features,
                        ],
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
