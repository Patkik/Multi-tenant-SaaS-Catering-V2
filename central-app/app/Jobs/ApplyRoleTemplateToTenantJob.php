<?php

namespace App\Jobs;

use App\Models\RoleTemplateApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApplyRoleTemplateToTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $roleTemplateApplicationId)
    {
    }

    public function handle(): void
    {
        $application = RoleTemplateApplication::query()->find($this->roleTemplateApplicationId);

        if ($application === null || $application->status === RoleTemplateApplication::STATUS_APPLIED) {
            return;
        }

        try {
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_APPLYING,
                'error_message' => null,
            ])->save();

            // Central-only status transition for now; tenant runtime integration is pending.
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_APPLIED,
                'applied_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $throwable) {
            $application->forceFill([
                'status' => RoleTemplateApplication::STATUS_FAILED,
                'error_message' => mb_substr($throwable->getMessage(), 0, 65535),
            ])->save();
        }
    }
}
