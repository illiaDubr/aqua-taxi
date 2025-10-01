<?php

// database/migrations/2025_10_01_000001_make_user_id_nullable_on_factory_orders.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // если есть FK, безопаснее снять и повесить заново
        try { Schema::table('factory_orders', fn (Blueprint $t) => $t->dropForeign(['user_id'])); } catch (\Throwable $e) {}

        // если в БД есть "0", переведём в NULL
        DB::statement('UPDATE factory_orders SET user_id = NULL WHERE user_id = 0');

        // сделать колонку nullable
        Schema::table('factory_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->default(null)->change();
        });

        // вернуть FK и ставим nullOnDelete (по желанию)
        try {
            Schema::table('factory_orders', function (Blueprint $t) {
                $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // откат (возвращать NOT NULL не обязательно, но можно)
        Schema::table('factory_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->default(0)->change();
        });
    }
};
