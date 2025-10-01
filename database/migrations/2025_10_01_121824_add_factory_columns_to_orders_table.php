<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // если таблица уже большая — добавляй после нужной колонки
            $table->foreignId('factory_id')
                ->nullable()
                ->constrained('factories')
                ->nullOnDelete(); // при удалении фабрики поле станет NULL

            // пригодится для статусов
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // индексы, чтобы выборки летали
            $table->index(['status', 'factory_id']);
            $table->index('accepted_at');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['orders_status_factory_id_index']);
            $table->dropIndex(['orders_accepted_at_index']);
            $table->dropIndex(['orders_completed_at_index']);
            $table->dropConstrainedForeignId('factory_id');
            $table->dropColumn(['accepted_at','completed_at']);
        });
    }
};
