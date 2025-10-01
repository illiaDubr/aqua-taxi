<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Если таблицы нет — создадим с нуля
        if (!Schema::hasTable('factory_orders')) {
            Schema::create('factory_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id');
                $table->unsignedBigInteger('factory_id');
                $table->string('water_type', 100);
                $table->decimal('price_per_bottle', 10, 2);
                $table->unsignedInteger('quantity');
                $table->decimal('total_price', 12, 2);
                $table->enum('status', ['new','accepted','completed'])->default('new');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['factory_id','status'], 'fo_fac_status_idx');
                $table->index(['driver_id','status'],  'fo_drv_status_idx');
                $table->index('updated_at',            'fo_updated_idx');

                $table->foreign('factory_id')->references('id')->on('factories')->cascadeOnDelete();
                // ВАЖНО: выбери правильную таблицу FK для драйвера
                $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
                // Если у тебя драйверы в users — используй on('users') вместо on('drivers')
            });

            // Опционально CHECK (MySQL 8+). Если не нужен — можно пропустить.
            try {
                DB::statement("
                    ALTER TABLE factory_orders
                    ADD CONSTRAINT chk_factory_orders_total_price
                    CHECK (total_price = price_per_bottle * quantity)
                ");
                DB::statement("
                    ALTER TABLE factory_orders
                    ADD CONSTRAINT chk_factory_orders_non_negative
                    CHECK (price_per_bottle >= 0 AND quantity >= 1 AND total_price >= 0)
                ");
            } catch (\Throwable $e) {
                // На некоторых MySQL/в режимах совместимости CHECK может игнориться
            }

            return;
        }

        // Иначе — таблица уже есть: добавим недостающие поля/индексы/ключи
        Schema::table('factory_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('factory_orders', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->after('id');
            }
            if (!Schema::hasColumn('factory_orders', 'factory_id')) {
                $table->unsignedBigInteger('factory_id')->after('driver_id');
            }
            if (!Schema::hasColumn('factory_orders', 'water_type')) {
                $table->string('water_type', 100)->after('factory_id');
            }
            if (!Schema::hasColumn('factory_orders', 'price_per_bottle')) {
                $table->decimal('price_per_bottle', 10, 2)->after('water_type');
            }
            if (!Schema::hasColumn('factory_orders', 'quantity')) {
                $table->unsignedInteger('quantity')->after('price_per_bottle');
            }
            if (!Schema::hasColumn('factory_orders', 'total_price')) {
                $table->decimal('total_price', 12, 2)->after('quantity');
            }
            if (!Schema::hasColumn('factory_orders', 'status')) {
                // добавляем как VARCHAR, если не хочешь ставить doctrine/dbal
                $table->string('status', 20)->default('new')->after('total_price');
                // Если нужен ENUM именно ENUM — лучше через отдельное raw-ALTER ниже.
            }
            if (!Schema::hasColumn('factory_orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('factory_orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('accepted_at');
            }
            if (!Schema::hasColumn('factory_orders', 'created_at')) {
                $table->timestamps();
            }

            // Индексы (ставим с именами, чтобы избежать конфликтов)
            try { $table->index(['factory_id','status'], 'fo_fac_status_idx'); } catch (\Throwable $e) {}
            try { $table->index(['driver_id','status'],  'fo_drv_status_idx'); }  catch (\Throwable $e) {}
            try { $table->index('updated_at',            'fo_updated_idx'); }      catch (\Throwable $e) {}

            // FK — только если нет (проверим ниже raw-ом)
        });

        // Приведение status к ENUM (опционально).
        // Без doctrine/dbal проще сделать RAW-ALTER, НО сначала проверим текущее определение:
        try {
            $col = DB::selectOne("
                SELECT COLUMN_TYPE AS type
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'factory_orders'
                   AND COLUMN_NAME = 'status'
            ");
            if ($col && stripos($col->type, 'enum(') === false) {
                // статус не ENUM — конвертируем
                DB::statement("ALTER TABLE factory_orders MODIFY COLUMN status ENUM('new','accepted','completed') NOT NULL DEFAULT 'new'");
            }
        } catch (\Throwable $e) {}

        // Внешние ключи — добавим, если их нет
        try {
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME
                  FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'factory_orders'
                   AND COLUMN_NAME = 'factory_id'
                   AND REFERENCED_TABLE_NAME = 'factories'
            ");
            if (!$fk) {
                DB::statement("
                    ALTER TABLE factory_orders
                    ADD CONSTRAINT fo_factory_fk
                    FOREIGN KEY (factory_id) REFERENCES factories(id)
                    ON DELETE CASCADE
                ");
            }
        } catch (\Throwable $e) {}

        // Выбери правильную таблицу FK для driver_id (drivers ИЛИ users)
        try {
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME
                  FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'factory_orders'
                   AND COLUMN_NAME = 'driver_id'
            ");
            if (!$fk) {
                DB::statement("
                    ALTER TABLE factory_orders
                    ADD CONSTRAINT fo_driver_fk
                    FOREIGN KEY (driver_id) REFERENCES drivers(id)
                    ON DELETE CASCADE
                ");
                // Если у тебя драйверы в users:
                // DB::statement(\"ALTER TABLE factory_orders
                //  ADD CONSTRAINT fo_driver_fk
                //  FOREIGN KEY (driver_id) REFERENCES users(id)
                //  ON DELETE CASCADE\");
            }
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Обычно down для update-миграции пустой, чтобы не сломать прод.
        // Если очень нужно — можно дропнуть добавленные индексы/колонки по именам.
    }
};
