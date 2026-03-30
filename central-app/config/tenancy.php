<?php

return [
    'provisioning_connection' => env('DB_PROVISIONING_CONNECTION', 'mysql_provisioning'),
    'runtime_connection' => env('TENANCY_RUNTIME_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'runtime_connection_alias' => env('TENANCY_RUNTIME_CONNECTION_ALIAS', 'tenant_runtime'),
    'startup_health_check_enabled' => env('TENANCY_STARTUP_HEALTH_CHECK_ENABLED', env('APP_ENV') === 'local'),
    'health_check_fail_fast' => env('TENANCY_HEALTH_CHECK_FAIL_FAST', false),
];
