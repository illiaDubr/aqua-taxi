<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');
            $table->string('website');
            $table->string('address')->nullable();
            $table->string('warehouse_address');
            $table->text('water_types')->nullable();
            $table->string('certificate_path'); // путь к PDF
            $table->boolean('is_verified')->default(false);
            $table->date('verified_until')->nullable(); // если не верифицирован — null
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factories');
    }
};

