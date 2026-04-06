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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('order_number');
            $table->string('customer_name');
            $table->unsignedInteger('items_count');
            $table->decimal('total_amount', 10, 2);
            $table->string('order_type', 20);
            $table->string('status', 20);
            $table->timestamp('ordered_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'ordered_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'order_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};