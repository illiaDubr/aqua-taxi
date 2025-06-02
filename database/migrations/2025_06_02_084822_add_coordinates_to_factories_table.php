<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_coordinates_to_factories_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('warehouse_address');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};

