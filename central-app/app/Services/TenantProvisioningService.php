<?php

namespace App\Services;

use App\Contracts\TenantDatabaseProvisioner;
use App\Exceptions\TenantProvisioningException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantProvisioningService
{
    public function __construct(private readonly TenantDatabaseProvisioner $tenantDatabaseProvisioner)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws TenantProvisioningException
     */
    public function createTenant(array $payload): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = DB::transaction(function () use ($payload): Tenant {
            return Tenant::query()->create([
                'name' => $payload['name'],
                'domain' => $payload['domain'],
                'database_name' => $payload['database_name'],
                'plan_code' => $payload['plan_code'] ?? null,
                'plan_entitlements' => $payload['plan_entitlements'] ?? [],
                'provisioning_status' => 'provisioning',
                'provisioning_error' => null,
                'provisioned_at' => null,
            ]);
        });

        try {
            $this->tenantDatabaseProvisioner->createDatabase((string) $tenant->database_name);
        } catch (Throwable $exception) {
            try {
                DB::transaction(function () use ($tenant, $exception): void {
                    $tenant->forceFill([
                        'provisioning_status' => 'failed',
                        'provisioning_error' => $this->normalizeErrorMessage($exception),
                        'provisioned_at' => null,
                    ])->save();
                });
            } catch (Throwable) {
                // Best-effort fallback only; preserve the original createDatabase failure signal.
            }

            throw new TenantProvisioningException('Tenant provisioning failed.', previous: $exception);
        }

        try {
            DB::transaction(function () use ($tenant): void {
                $tenant->forceFill([
                    'provisioning_status' => 'ready',
                    'provisioning_error' => null,
                    'provisioned_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            try {
                DB::transaction(function () use ($tenant, $exception): void {
                    $tenant->forceFill([
                        'provisioning_status' => 'failed',
                        'provisioning_error' => $this->normalizeErrorMessage($exception),
                        'provisioned_at' => null,
                    ])->save();
                });
            } catch (Throwable) {
                // Best-effort fallback only; preserve the original ready-state failure signal.
            }

            throw new TenantProvisioningException('Tenant provisioning failed.', previous: $exception);
        }

        return $tenant->fresh();
    }

    private function normalizeErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'Unknown tenant database provisioning error.';
        }

        return mb_substr($message, 0, 2000);
    }
}
