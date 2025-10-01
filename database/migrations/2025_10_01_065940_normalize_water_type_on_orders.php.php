<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // product_name (если нет)
        if (!Schema::hasColumn('orders', 'product_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('product_name')->nullable()->after('address');
            });
        }

        // если water_type нет — просто добавим с нужным набором
        if (!Schema::hasColumn('orders', 'water_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('water_type', ['silver','deep'])->after('product_name');
            });
            return;
        }

        // маппинг старых значений в новые
        DB::table('orders')->where('water_type', 'Срібна')->update(['water_type' => 'silver']);
        DB::table('orders')->whereIn('water_type', ['Глибокого очищення','Глибокого очищення, 19л'])->update(['water_type' => 'deep']);

        // меняем тип на enum(silver,deep)
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('water_type', ['silver','deep'])->change();
        });
    }

    public function down(): void
    {
        // откатим в string, чтобы не терять данные при даунгрейде
        Schema::table('orders', function (Blueprint $table) {
            $table->string('water_type', 64)->change();
        });
    }
};
