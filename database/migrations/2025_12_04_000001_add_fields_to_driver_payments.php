<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('driver_id');

            // PID из iPay — уникальный ID платежа
            $table->string('order_id')->unique();

            // Сумма в гривнах
            $table->decimal('amount', 10, 2);

            // Сумма в копейках
            $table->integer('amount_cents');

            // Статус: created, pending, success, fail
            $table->string('status')->default('created');

            // Был ли зачислен баланс водителю
            $table->boolean('credited')->default(false);

            // Полный XML ответ
            $table->longText('response_data')->nullable();

            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payments');
    }
};
