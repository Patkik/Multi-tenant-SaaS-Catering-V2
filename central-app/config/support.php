<?php

return [
    'default_recipient' => env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@caterpro.local')),
    'central_recipient' => env('CENTRAL_SUPPORT_EMAIL', env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@caterpro.local'))),
    'tenant_recipient' => env('TENANT_SUPPORT_EMAIL', env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@caterpro.local'))),
    'from_address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'CaterPro')),
];