<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factory_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            
            $table->string('order_id')->unique(); // внутренний ID
            $table->string('pid')->nullable();    // настоящий PID от iPay
            
            $table->decimal('amount', 10, 2)->default(0);
            $table->integer('amount_cents')->default(0);
            
            $table->string('status')->default('pending'); // pending | success | failed …
            $table->boolean('credited')->default(false);

            $table->longText('response_data')->nullable();

            $table->timestamps();

            $table->foreign('factory_id')->references('id')->on('factories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_payments');
    }
};
