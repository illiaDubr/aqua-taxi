<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');  // кто создал
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete(); // кто взял
            $table->enum('status', ['new', 'in_progress', 'completed', 'cancelled'])->default('new');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['user_id', 'driver_id', 'status']);
        });
    }
};
