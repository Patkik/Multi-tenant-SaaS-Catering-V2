<?php

namespace App\Services;

use App\Jobs\ApplyRoleTemplateToTenantJob;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateApplication;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoleTemplateApplicationService
{
    /**
     * @param array{strategy:string, idempotency_key?:string|null, requested_by_admin?:string|null} $payload
     */
    public function queueApplication(Tenant $tenant, RoleTemplate $roleTemplate, array $payload): RoleTemplateApplication
    {
        /** @var RoleTemplateApplication $application */
        $application = DB::transaction(function () use ($tenant, $roleTemplate, $payload): RoleTemplateApplication {
            $idempotencyKey = $payload['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = RoleTemplateApplication::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('role_template_id', $roleTemplate->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            try {
                $application = RoleTemplateApplication::query()->create([
                    'tenant_id' => $tenant->id,
                    'role_template_id' => $roleTemplate->id,
                    'strategy' => $payload['strategy'],
                    'status' => RoleTemplateApplication::STATUS_QUEUED,
                    'idempotency_key' => $idempotencyKey,
                    'requested_by_admin' => $payload['requested_by_admin'] ?? null,
                    'error_message' => null,
                    'applied_at' => null,
                ]);
            } catch (QueryException $exception) {
                if ($idempotencyKey === null || ! $this->isDuplicateIdempotencyConstraintViolation($exception)) {
                    throw $exception;
                }

                /** @var RoleTemplateApplication $existing */
                $existing = RoleTemplateApplication::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('role_template_id', $roleTemplate->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();

                return $existing;
            }

            ApplyRoleTemplateToTenantJob::dispatch($application->id)->afterCommit();

            return $application;
        });

        return $application;
    }

    private function isDuplicateIdempotencyConstraintViolation(QueryException $exception): bool
    {
        $errorCode = (string) ($exception->errorInfo[1] ?? '');
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = Str::lower($exception->getMessage());

        $targetsIdempotencyConstraint =
            str_contains($message, 'role_template_applications_tenant_id_role_template_id_idempotency_key_unique') ||
            str_contains($message, 'rta_tenant_template_idempotency_unique') ||
            (str_contains($message, 'role_template_applications') && str_contains($message, 'idempotency_key'));

        if (! $targetsIdempotencyConstraint) {
            return false;
        }

        if ($sqlState === '23505') {
            return true;
        }

        if ($errorCode === '1062') {
            return str_contains($message, 'duplicate') || str_contains($message, 'for key');
        }

        if ($sqlState === '23000') {
            return str_contains($message, 'duplicate') || str_contains($message, 'unique');
        }

        return false;
    }
}
