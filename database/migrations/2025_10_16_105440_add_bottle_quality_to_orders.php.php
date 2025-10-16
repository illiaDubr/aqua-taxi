<?php

// database/migrations/2025_10_16_000000_add_bottle_quality_to_orders.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // качество бутыля, только если bottle_option = 'own'
            $table->enum('bottle_quality', ['ideal','average','bad'])
                ->nullable()
                ->after('bottle_option');

            // число купленных бутлей (если bottle_option = 'buy')
            $table->unsignedInteger('purchase_bottle_count')
                ->default(0)
                ->after('bottle_quality');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bottle_quality', 'purchase_bottle_count']);
        });
    }
};
