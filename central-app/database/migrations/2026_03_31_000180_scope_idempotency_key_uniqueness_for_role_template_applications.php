<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_template_applications', function (Blueprint $table): void {
            $table->dropUnique('role_template_applications_idempotency_key_unique');
            $table->unique([
                'tenant_id',
                'role_template_id',
                'idempotency_key',
            ], 'rta_tenant_template_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep rollback data-safe: restore global uniqueness by rewriting only colliding keys.
        $duplicateKeys = DB::table('role_template_applications')
            ->select('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('idempotency_key');

        foreach ($duplicateKeys as $idempotencyKey) {
            $collidingIds = DB::table('role_template_applications')
                ->where('idempotency_key', $idempotencyKey)
                ->orderBy('id')
                ->pluck('id')
                ->values();

            // Preserve the earliest row's original key; rewrite later collisions.
            foreach ($collidingIds->slice(1) as $applicationId) {
                $suffix = '--' . $applicationId;
                $maxBaseLength = max(1, 255 - strlen($suffix));
                $safeKey = substr((string) $idempotencyKey, 0, $maxBaseLength) . $suffix;

                DB::table('role_template_applications')
                    ->where('id', $applicationId)
                    ->update([
                        'idempotency_key' => $safeKey,
                        'updated_at' => now(),
                    ]);
            }
        }

        Schema::table('role_template_applications', function (Blueprint $table): void {
            $table->dropUnique('rta_tenant_template_idempotency_unique');
            $table->unique('idempotency_key');
        });
    }
};
