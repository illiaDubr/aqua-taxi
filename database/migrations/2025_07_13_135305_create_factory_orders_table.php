<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('factory_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // клиент
            $table->foreignId('factory_id')->constrained();
            $table->string('water_type');
            $table->decimal('price_per_bottle', 8, 2);
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['new', 'in_progress', 'completed'])->default('new');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('factory_orders');
    }
};
