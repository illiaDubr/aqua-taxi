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

            // внутренний ID заказа/начисления
            $table->unsignedBigInteger('order_id')->nullable();

            // ID платежа на стороне iPay
            $table->string('transaction_id')->nullable();

            // Сумма
            $table->decimal('amount', 10, 2);

            // Тип операции: пополнение, выплата, штраф, возврат
            $table->enum('type', [
                'topup',
                'order_payout',
                'penalty',
                'refund'
            ])->default('topup');

            // URL на оплату
            $table->string('payment_url')->nullable();

            // Статус: pending → processing → success/failed
            $table->enum('status', [
                'pending',
                'processing',
                'success',
                'failed'
            ])->default('pending');

            // Причина ошибки или текст от платёжки
            $table->text('message')->nullable();

            // Полный JSON от iPay
            $table->json('response_data')->nullable();

            $table->timestamps();

            // Внешний ключ
            $table->foreign('driver_id')
                ->references('id')->on('drivers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payments');
    }
};
