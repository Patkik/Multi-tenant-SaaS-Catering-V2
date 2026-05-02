<?php

/**
 * CaterPro Database Configuration - LAN Multi-Tenancy "Pristine Blueprint" Strategy
 * 
 * Purpose: Define three database connections that implement the Blueprint pattern:
 * 1. mysql_landlord: Central management database (shared by all tenants)
 * 2. mysql_blueprint: Read-only template for tenant schema (pristine copy)
 * 3. dynamic_tenant: Per-tenant databases (created per request)
 * 
 * Architecture:
 * - Landlord DB: Stores central app data (users, tenants, billing, audit logs)
 * - Blueprint DB: Template schema used to create fresh tenant databases
 * - Tenant DBs: Individual SQLite files or MySQL databases per tenant
 * 
 * LAN Deployment Notes:
 * - All databases run on localhost (192.168.1.100 in .env)
 * - Blueprint connection is READ-ONLY to prevent accidental modifications
 * - Tenant connections use SQLite file paths (storage/tenants/{id}.sqlite)
 * - MySQL Strict mode enabled to catch schema violations early
 */

use Illuminate\Support\Str;
use PDO;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    | This is the connection used for landlord/central app operations.
    */
    'default' => env('DB_CONNECTION', 'landlord'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Three primary connections for multi-tenant architecture:
    | 1. landlord - Central database (user management, tenant registry)
    | 2. mysql_landlord - Explicit MySQL connection (LAN failover)
    | 3. mysql_blueprint - Template schema for tenant creation
    | 4. dynamic_tenant - Per-tenant SQLite/MySQL connections
    | 5. sqlite - Local SQLite fallback (dev/testing)
    |
    */

    'connections' => [

        /**
         * SQLite Connection (Development/Testing Fallback)
         */
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        /**
         * MySQL Connection (Default Fallback)
         */
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /**
         * LANDLORD DATABASE (Central Management)
         * 
         * Stores:
         * - System users (admins, support staff)
         * - Tenant registry and metadata
         * - Billing and subscription information
         * - Audit logs and system events
         * - Permissions and roles (Spatie)
         * 
         * LAN Notes:
         * - Single database on localhost
         * - Regular backups required (contains critical business data)
         * - Used for central API routes (/api/central/*)
         */
        'landlord' => env('DB_LANDLORD_DRIVER', 'mysql') === 'sqlite'
            ? [
                'driver' => 'sqlite',
                'url' => env('DB_LANDLORD_URL'),
                'database' => env('DB_LANDLORD_DATABASE', database_path('landlord.sqlite')),
                'prefix' => '',
                'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ]
            : [
                'driver' => env('DB_LANDLORD_DRIVER', 'mysql'),
                'url' => env('DB_LANDLORD_URL'),
                'host' => env('DB_LANDLORD_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('DB_LANDLORD_PORT', env('DB_PORT', '3306')),
                'database' => env('DB_LANDLORD_DATABASE', 'caterpro_landlord'),
                'username' => env('DB_LANDLORD_USERNAME', env('DB_USERNAME', 'root')),
                'password' => env('DB_LANDLORD_PASSWORD', env('DB_PASSWORD', '')),
                'unix_socket' => env('DB_LANDLORD_SOCKET', env('DB_SOCKET', '')),
                'charset' => env('DB_LANDLORD_CHARSET', env('DB_CHARSET', 'utf8mb4')),
                'collation' => env('DB_LANDLORD_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true, // CRITICAL: Catch schema violations early
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ],

        /**
         * EXPLICIT MYSQL_LANDLORD (Backup Connection)
         * 
         * Secondary connection for LAN failover scenarios.
         * If primary 'landlord' connection fails, fallback to this.
         */
        'mysql_landlord' => [
            'driver' => 'mysql',
            'host' => env('DB_LANDLORD_HOST', 'localhost'),
            'port' => env('DB_LANDLORD_PORT', '3306'),
            'database' => env('DB_LANDLORD_DATABASE', 'caterpro_landlord'),
            'username' => env('DB_LANDLORD_USERNAME', 'caterpro_user'),
            'password' => env('DB_LANDLORD_PASSWORD', 'changeme'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],

        /**
         * BLUEPRINT DATABASE (Read-Only Template)
         * 
         * Purpose: Pristine schema template for tenant database creation
         * 
         * Process:
         * 1. Run migrations on blueprint to establish baseline schema
         * 2. When new tenant created, clone blueprint structure
         * 3. Blueprint remains read-only to prevent corruption
         * 
         * LAN Strategy:
         * - Same MySQL host as landlord (localhost)
         * - Separate database: caterpro_blueprint
         * - Initialized during first deployment
         * - Updated when migrations are added (stancl/tenancy handles this)
         * 
         * CRITICAL: Do NOT create tenants from this database!
         * The stancl/tenancy package uses the 'tenant_template' connection instead.
         */
        'mysql_blueprint' => [
            'driver' => 'mysql',
            'host' => env('DB_LANDLORD_HOST', 'localhost'),
            'port' => env('DB_LANDLORD_PORT', '3306'),
            'database' => env('DB_TENANT_TEMPLATE_DATABASE', 'caterpro_blueprint'),
            'username' => env('DB_LANDLORD_USERNAME', 'caterpro_user'),
            'password' => env('DB_LANDLORD_PASSWORD', 'changeme'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],

        /**
         * TENANT TEMPLATE CONNECTION (Stancl/Tenancy)
         * 
         * This is the connection used by stancl/tenancy to create new tenant databases.
         * 
         * CRITICAL NOTES FROM REPO MEMORY:
         * "Do NOT use reserved `tenant` connection as `template_tenant_connection`; 
         *  use a separate template connection (e.g., `tenant_template`) or tenant 
         *  migrations can fail with DatabaseManagerNotRegisteredException."
         * 
         * Architecture:
         * - Stancl creates databases by copying this connection's schema
         * - Can be MySQL or SQLite
         * - Must be writable (unlike mysql_blueprint)
         * - Best practice: Use separate database per environment (dev/staging/prod)
         */
        'tenant_template' => env('DB_TENANT_DRIVER', 'mysql') === 'sqlite'
            ? [
                'driver' => 'sqlite',
                'url' => env('DB_TENANT_TEMPLATE_URL'),
                'database' => env('DB_TENANT_TEMPLATE_DATABASE', database_path('tenants/template.sqlite')),
                'prefix' => '',
                'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ]
            : [
                'driver' => env('DB_TENANT_DRIVER', 'mysql'),
                'url' => env('DB_TENANT_TEMPLATE_URL'),
                'host' => env('DB_LANDLORD_HOST', 'localhost'),
                'port' => env('DB_LANDLORD_PORT', '3306'),
                'database' => env('DB_TENANT_TEMPLATE_DATABASE', 'caterpro_tenant_template'),
                'username' => env('DB_LANDLORD_USERNAME', 'caterpro_user'),
                'password' => env('DB_LANDLORD_PASSWORD', 'changeme'),
                'unix_socket' => env('DB_LANDLORD_SOCKET', ''),
                'charset' => env('DB_TENANT_TEMPLATE_CHARSET', 'utf8mb4'),
                'collation' => env('DB_TENANT_TEMPLATE_COLLATION', 'utf8mb4_unicode_ci'),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ],

        /**
         * DYNAMIC TENANT CONNECTION (Per-Tenant)
         * 
         * This connection is dynamically created per request based on which tenant
         * is being accessed. Stancl/tenancy updates the connection parameters
         * at runtime when a tenant request is detected.
         * 
         * LAN Deployment Strategy:
         * - Driver: SQLite (files in /mnt/caterpro_shared/tenants/{id}.sqlite)
         * - Database naming: tenant_{tenant_id}
         * - Synced across LAN via shared NFS mount
         * 
         * Process Flow:
         * 1. Request arrives: acme.caterpro.local
         * 2. InitializeTenancyBySubdomain extracts "acme" subdomain
         * 3. Queries landlord DB: SELECT id FROM tenants WHERE ... subdomain = 'acme'
         * 4. Stancl updates 'tenant' connection: database = /mnt/caterpro_shared/tenants/{acme_id}.sqlite
         * 5. Query executes on tenant-specific database
         */
        'tenant' => [
            'driver' => env('DB_TENANT_DRIVER', 'sqlite'),
            'url' => env('DATABASE_URL'),
            'database' => env('DB_TENANT_DATABASE', storage_path('tenants/default.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        /**
         * SECONDARY DYNAMIC TENANT (MySQL Alternative)
         * 
         * If you prefer MySQL per tenant (not recommended for LAN):
         * Use this configuration and set DB_TENANT_DRIVER=mysql in .env
         * 
         * Considerations:
         * - Requires N MySQL databases to be pre-created
         * - Higher resource usage than SQLite
         * - Connection pooling becomes critical
         * - Not recommended for LAN with 10+ tenants
         */
        'dynamic_tenant_mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_TENANT_HOST', 'localhost'),
            'port' => env('DB_TENANT_PORT', '3306'),
            'database' => env('DB_TENANT_DATABASE', 'tenant_default'),
            'username' => env('DB_TENANT_USERNAME', 'caterpro_user'),
            'password' => env('DB_TENANT_PASSWORD', 'changeme'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis "Cluster" Configuration
    |--------------------------------------------------------------------------
    | Here you may configure your Redis database connections. Redis is an
    | extremely fast and powerful in-memory data store that works
    | wonderfully for caching, sessions, queuing, and more.
    |
    | For LAN deployments: Use Unix socket for best performance
    | REDIS_HOST=unix:/run/redis/redis.sock
    | REDIS_PORT=0
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'caterpro_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
            'read_timeout' => 0.0,
            'connection_pool_size' => 10,
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_SESSION_DB', 2),
        ],

        'queue' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_QUEUE_DB', 3),
        ],
    ],

];
