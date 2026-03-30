<?php

namespace App\Support;

final class FeatureCategories
{
    public const CORE = 'Core';
    public const CRM = 'CRM';
    public const BILLING = 'Billing';
    public const REPORTING = 'Reporting';
    public const INTEGRATION = 'Integration';
    public const ADMIN = 'Admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CORE,
            self::CRM,
            self::BILLING,
            self::REPORTING,
            self::INTEGRATION,
            self::ADMIN,
        ];
    }
}
