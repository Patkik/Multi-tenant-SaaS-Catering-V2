<?php

namespace App\Jobs;

use App\Models\RoleTemplateApplication;
use App\Services\RoleTemplateSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApplyRoleTemplateToTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $roleTemplateApplicationId)
    {
    }

    public function handle(RoleTemplateSyncService $syncService): void
    {
        $application = RoleTemplateApplication::query()->find($this->roleTemplateApplicationId);

        if ($application === null || $application->status === RoleTemplateApplication::STATUS_APPLIED) {
            return;
        }

        try {
            // Transition to APPLYING status
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_APPLYING,
                'error_message' => null,
            ])->save();

            // Fetch related models from central database
            $tenant = $application->tenant()->firstOrFail();
            $roleTemplate = $application->roleTemplate()->firstOrFail();

            // Execute the actual sync to tenant database
            $syncSummary = $syncService->syncRoleTemplate(
                $tenant,
                $roleTemplate,
                $application->strategy,
            );

            // Transition to APPLIED on success
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_APPLIED,
                'applied_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $throwable) {
            // Transition to FAILED and record error message
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_FAILED,
                'error_message' => mb_substr($throwable->getMessage(), 0, 65535),
            ])->save();

            // Log the error for observability
            \Illuminate\Support\Facades\Log::error(
                "ApplyRoleTemplateToTenantJob failed for application {$this->roleTemplateApplicationId}",
                [
                    'application_id' => $this->roleTemplateApplicationId,
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ],
            );

            // Re-throw for queue retry logic (if configured)
            throw $throwable;
        }
    }
}
