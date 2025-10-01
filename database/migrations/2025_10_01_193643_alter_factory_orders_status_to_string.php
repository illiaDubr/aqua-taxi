<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('factory_orders', function (Blueprint $table) {
            // строка до 32 символов, индексация и дефолт
            $table->string('status', 32)->default('new')->index()->change();
            $table->timestamp('accepted_at')->nullable()->change();
            $table->timestamp('completed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        // если раньше был ENUM — верни как было, при необходимости
        Schema::table('factory_orders', function (Blueprint $table) {
            // пример отката, подстрой под то, что было изначально
            // $table->enum('status', ['new','in_progress','completed','canceled'])->default('new')->change();
        });
    }
};

