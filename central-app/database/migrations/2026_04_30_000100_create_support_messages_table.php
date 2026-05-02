<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 20);
                $table->string('category', 40);
                $table->string('subject', 120);
                $table->text('message');
                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('workspace_name')->nullable();
                $table->string('workspace_id')->nullable();
                $table->string('tenant_id')->nullable();
                $table->string('page_path')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->string('user_role')->nullable();
                $table->string('tenant_domain')->nullable();
                $table->string('request_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['source', 'created_at']);
                $table->index('tenant_id');
                $table->index('workspace_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
