<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catering_packages', function (Blueprint $table): void {
            $table->json('menu_items')->nullable()->after('is_active');
            $table->timestamp('menu_published_at')->nullable()->after('menu_items');
        });
    }

    public function down(): void
    {
        Schema::table('catering_packages', function (Blueprint $table): void {
            $table->dropColumn(['menu_items', 'menu_published_at']);
        });
    }
};
