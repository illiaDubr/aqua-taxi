<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('factories', 'water_types')) {
            Schema::table('factories', function (Blueprint $table) {
                // Храним цены в JSON-массиве объектов:
                // [
                //   { "code": "silver", "name": "Срібна", "price": 33.5 },
                //   { "code": "deep",   "name": "Глибокого очищення", "price": 28.0 }
                // ]
                $table->json('water_types')->nullable()->after('website');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('factories', 'water_types')) {
            Schema::table('factories', function (Blueprint $table) {
                $table->dropColumn('water_types');
            });
        }
    }
};
