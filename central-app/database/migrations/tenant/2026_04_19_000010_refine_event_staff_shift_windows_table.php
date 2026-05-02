<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasShiftStart = Schema::hasColumn('event_staff', 'shift_start_at');
        $hasShiftEnd = Schema::hasColumn('event_staff', 'shift_end_at');

        Schema::table('event_staff', function (Blueprint $table) use ($hasShiftStart, $hasShiftEnd): void {
            if (! $hasShiftStart) {
                $table->dateTime('shift_start_at')->nullable()->after('assignment_role');
            }

            if (! $hasShiftEnd) {
                $table->dateTime('shift_end_at')->nullable()->after('shift_start_at');
            }

            $table->index(['staff_id', 'shift_start_at', 'shift_end_at'], 'event_staff_shift_window_idx');
        });

        DB::table('event_staff')->update([
            'shift_start_at' => DB::raw('COALESCE(shift_start_at, start_time)'),
            'shift_end_at' => DB::raw('COALESCE(shift_end_at, end_time)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('event_staff', function (Blueprint $table): void {
            $table->dropIndex('event_staff_shift_window_idx');
            $table->dropColumn(['shift_start_at', 'shift_end_at']);
        });
    }
};
