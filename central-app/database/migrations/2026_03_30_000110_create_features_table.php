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
        Schema::create('features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('category', ['Core', 'CRM', 'Billing', 'Reporting', 'Integration', 'Admin']);
            $table->boolean('default_enabled')->default(true);
            $table->string('requires_plan')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('requires_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
