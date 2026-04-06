<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantAccountRegistrationService
{
    /**
     * @param array{email:string,password:string,role:string,full_name:string} $payload
     * @return array{connection:string,status:string}
     */
    public function register(Tenant $tenant, array $payload): array
    {
        $connectionName = $this->resolveTenantRuntimeConnection($tenant);
        $normalizedEmail = $this->normalizeEmail($payload['email']);
        $status = $payload['role'] === 'admin' ? 'pending' : 'active';
        $isActive = $status === 'active';
        $now = now();

        DB::connection($connectionName)->transaction(function () use ($connectionName, $payload, $normalizedEmail, $isActive, $now): void {
            $usersTable = DB::connection($connectionName)->table('users');
            $usersHasRoleColumn = Schema::connection($connectionName)->hasColumn('users', 'role');
            $usersHasIsActiveColumn = Schema::connection($connectionName)->hasColumn('users', 'is_active');

            if ($usersTable->whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
                throw new RuntimeException('An account with this email already exists.');
            }

            $insertPayload = [
                'name' => $payload['full_name'],
                'email' => $normalizedEmail,
                'password' => Hash::make($payload['password']),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($usersHasRoleColumn) {
                $insertPayload['role'] = $payload['role'];
            }

            if ($usersHasIsActiveColumn) {
                $insertPayload['is_active'] = $isActive;
            }

            $usersTable->insert($insertPayload);
        });

        return [
            'connection' => $connectionName,
            'status' => $status,
        ];
    }

    private function resolveTenantRuntimeConnection(Tenant $tenant): string
    {
        $runtimeConnection = (string) config('tenancy.runtime_connection', config('database.default'));
        $runtimeConnectionAlias = (string) config('tenancy.runtime_connection_alias', 'tenant_runtime');

        $runtimeConnectionConfig = config("database.connections.{$runtimeConnection}");

        if (! is_array($runtimeConnectionConfig)) {
            throw new RuntimeException(sprintf('Runtime tenant connection "%s" is not configured.', $runtimeConnection));
        }

        $runtimeConnectionConfig['database'] = (string) $tenant->database_name;

        config(["database.connections.{$runtimeConnectionAlias}" => $runtimeConnectionConfig]);

        DB::purge($runtimeConnectionAlias);
        DB::connection($runtimeConnectionAlias)->getPdo();

        return $runtimeConnectionAlias;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}