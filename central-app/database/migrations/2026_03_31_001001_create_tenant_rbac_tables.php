<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if tenant_rbac tables already created
        if (Schema::hasTable('tenant_roles')) {
            return;
        }

        // Tenant Roles table
        Schema::create('tenant_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_protected')->default(false); // Can't delete system roles
            $table->timestamps();

            $table->index('name');
        });

        // Permissions table
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique(); // e.g., 'orders.view', 'orders.create'
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general'); // orders, clients, staff, analytics, etc.
            $table->timestamps();

            $table->index(['category']);
        });

        // Role-Permission mapping
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('tenant_roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'role_perm_unique');
            $table->index('role_id');
            $table->index('permission_id');
        });

        // Tenant Features table
        Schema::create('tenant_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general'); // dashboard, orders, analytics, etc.
            $table->boolean('is_enabled_by_default')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        // Role-Feature mapping (which features are available to which roles)
        Schema::create('role_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('tenant_roles')->cascadeOnDelete();
            $table->foreignUuid('feature_id')->constrained('tenant_features')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'feature_id'], 'role_feat_unique');
            $table->index('role_id');
            $table->index('feature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_features');
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('tenant_roles');
    }
};
