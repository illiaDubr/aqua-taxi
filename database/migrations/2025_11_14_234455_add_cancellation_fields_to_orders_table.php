<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->string('cancelled_by')->nullable()->after('cancelled_at'); // 'user' | 'driver' | 'system'
            $table->unsignedBigInteger('cancelled_by_id')->nullable()->after('cancelled_by');
            $table->text('cancel_reason')->nullable()->after('cancelled_by_id');
            $table->index(['cancelled_at']);
        });
    }

    public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropIndex('orders_cancelled_at_index');
        $table->dropColumn([
            'cancelled_at',
            'cancelled_by',
            'cancelled_by_id',
            'cancel_reason',
        ]);
    });
}

};
