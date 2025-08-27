<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'water_type')) {
                $t->enum('water_type', ['Срібна','Глибокого очищення'])->nullable()->after('delivery_option');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (Schema::hasColumn('orders', 'water_type')) {
                $t->dropColumn('water_type');
            }
        });
    }
};
