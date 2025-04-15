<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // клиент
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete(); // водитель

            $table->enum('type', ['silver', 'deep_clean']); // тип воды
            $table->integer('bottles_count'); // количество бутылей

            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->enum('status', ['pending', 'accepted', 'on_way', 'delivered', 'cancelled'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
