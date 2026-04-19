<?php

namespace App\Support;

class CentralPermissions
{
    public const DASHBOARD_VIEW = 'central.dashboard.view';

    public const PLANS_VIEW = 'central.plans.view';

    public const TENANTS_VIEW = 'central.tenants.view';

    public const TENANTS_MANAGE = 'central.tenants.manage';

    public static function all(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::PLANS_VIEW,
            self::TENANTS_VIEW,
            self::TENANTS_MANAGE,
        ];
    }
}