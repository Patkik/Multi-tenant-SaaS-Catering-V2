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
        Schema::create('role_template_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_template_id')->constrained('role_templates')->cascadeOnDelete();
            $table->string('role_name', 100);
            $table->string('feature_key');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('feature_key')->references('name')->on('features')->cascadeOnDelete();
            $table->unique(['role_template_id', 'role_name', 'feature_key']);
            $table->index('feature_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_template_features');
    }
};
