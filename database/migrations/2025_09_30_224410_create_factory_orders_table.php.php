<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factory_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('factory_id')
                ->constrained('factories');

            $table->string('water_type', 255);
            $table->decimal('price_per_bottle', 8, 2);
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);

            $table->string('status', 32)->default('new');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Индексы
            $table->index(['factory_id', 'status'], 'fo_fac_status_idx');
            $table->index(['driver_id', 'status'], 'fo_drv_status_idx');
            $table->index('updated_at', 'fo_updated_idx');
            $table->index('status', 'factory_orders_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_orders');
    }
};
