<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_plan_overrides')) {
            return;
        }

        Schema::create('central_plan_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_key')->unique();
            $table->unsignedInteger('monthly_price')->default(0);
            $table->integer('user_limit')->nullable();
            $table->integer('monthly_active_event_limit')->nullable();
            $table->json('features');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_plan_overrides');
    }
};