<?php

namespace App\Support;

class PlanFeatures
{
    private const PLAN_DEFINITIONS = [
        'free' => [
            'label' => 'Free',
            'monthly_price' => 0,
            'monthly_active_event_limit' => 10,
            'features' => [
                self::EVENT_MANAGEMENT,
            ],
        ],
        'starter' => [
            'label' => 'Starter',
            'monthly_price' => 699,
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

    public static function forPlan(string $plan): array
    {
        $normalizedPlan = self::normalizePlan($plan);

        return self::PLAN_DEFINITIONS[$normalizedPlan]['features'];
    }

    public static function plans(): array
    {
        return self::PLAN_DEFINITIONS;
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
}
