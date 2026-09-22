<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración correctiva: 2026_09_22_120000_make_work_order_shop_nullable
 * quedó registrada como corrida pero en producción retread_shop_id siguió
 * NOT NULL (se confirmó en vivo: crear una OT sin recapadora tiraba 500 por
 * violación de constraint, mientras que con recapadora seguía funcionando
 * bien — la columna, no el código, era el problema).
 *
 * Esta migración es idempotente: si la columna ya es nullable, no hace
 * nada. Si no lo es, busca el nombre REAL del constraint FK en
 * information_schema en vez de asumir la convención de nombres de Laravel
 * (la causa más probable de que la migración anterior no haya alterado la
 * columna de verdad).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('retread_shop_id')->nullable()->change();
            });

            return;
        }

        if ($this->isNullable()) {
            return;
        }

        $this->dropRealForeignKey();

        DB::statement('ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED NULL');

        DB::statement(
            'ALTER TABLE `work_orders` '.
            'ADD CONSTRAINT `work_orders_retread_shop_id_foreign` '.
            'FOREIGN KEY (`retread_shop_id`) REFERENCES `retread_shops` (`id`) ON DELETE RESTRICT'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            DB::table('work_orders')->whereNull('retread_shop_id')->delete();
            Schema::table('work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('retread_shop_id')->nullable(false)->change();
            });

            return;
        }

        DB::table('work_orders')->whereNull('retread_shop_id')->delete();

        if (! $this->isNullable()) {
            return;
        }

        $this->dropRealForeignKey();

        DB::statement('ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED NOT NULL');

        DB::statement(
            'ALTER TABLE `work_orders` '.
            'ADD CONSTRAINT `work_orders_retread_shop_id_foreign` '.
            'FOREIGN KEY (`retread_shop_id`) REFERENCES `retread_shops` (`id`) ON DELETE RESTRICT'
        );
    }

    private function isNullable(): bool
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'work_orders')
            ->where('COLUMN_NAME', 'retread_shop_id')
            ->value('IS_NULLABLE') === 'YES';
    }

    private function dropRealForeignKey(): void
    {
        $fkName = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'work_orders')
            ->where('COLUMN_NAME', 'retread_shop_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($fkName) {
            DB::statement("ALTER TABLE `work_orders` DROP FOREIGN KEY `{$fkName}`");
        }
    }
};
