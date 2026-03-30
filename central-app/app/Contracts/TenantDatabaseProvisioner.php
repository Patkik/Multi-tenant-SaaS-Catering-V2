<?php

namespace App\Contracts;

interface TenantDatabaseProvisioner
{
    public function createDatabase(string $databaseName): void;
}
