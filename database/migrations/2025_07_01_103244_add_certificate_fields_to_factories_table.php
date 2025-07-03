<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->string('certificate_file')->nullable()->after('address'); // путь к сертификату
            $table->enum('certificate_status', ['pending', 'valid', 'invalid'])->default('pending')->after('certificate_file'); // статус
            $table->date('certificate_expiration')->nullable()->after('certificate_status'); // дата окончания
        });
    }

    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropColumn(['certificate_file', 'certificate_status', 'certificate_expiration']);
        });
    }
};
