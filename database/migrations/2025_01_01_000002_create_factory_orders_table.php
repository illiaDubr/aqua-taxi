<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factory_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('factory_id');

            $table->string('status')->default('pending'); // pending / accepted / cancelled / done
            $table->string('cancel_reason')->nullable();

            $table->integer('bottles')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->timestamps();

            $table->foreign('factory_id')->references('id')->on('factories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_orders');
    }
};
