<?php

namespace App\Services;

use App\Contracts\TenantDatabaseProvisioner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class MySqlTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function createDatabase(string $databaseName): void
    {
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $databaseName)) {
            throw new InvalidArgumentException('Database name may only contain letters, numbers, and underscores.');
        }

        if (strlen($databaseName) > 64) {
            throw new InvalidArgumentException('Database name must not exceed 64 characters.');
        }

        $connection = DB::connection((string) config('tenancy.provisioning_connection', config('database.default')));

        $databaseExists = $connection->selectOne(
            'SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
            [$databaseName]
        );

        if ($databaseExists !== null) {
            throw new RuntimeException(sprintf('Tenant database "%s" already exists.', $databaseName));
        }

        $identifier = sprintf('`%s`', str_replace('`', '``', $databaseName));

        $connection->statement(sprintf('CREATE DATABASE %s', $identifier));
    }
}
