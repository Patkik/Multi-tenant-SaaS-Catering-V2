<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_template_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_template_id')->constrained('role_templates')->cascadeOnDelete();
            $table->string('role_name', 100);
            $table->string('permission');
            $table->timestamps();

            $table->unique(['role_template_id', 'role_name', 'permission'], 'rt_perm_unique');
            $table->index('role_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_template_permissions');
    }
};
