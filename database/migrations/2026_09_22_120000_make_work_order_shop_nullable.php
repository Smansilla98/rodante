<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una Orden de Trabajo puede ser interna (reparación/recapado hecho por
 * personal propio en el taller de la empresa, sin recapadora externa).
 * retread_shop_id nulo = orden interna; con valor = taller externo.
 *
 * Reescrita para que NUNCA tire una excepción sin capturar. La versión
 * original dejaba el DROP FOREIGN envuelto en try/catch pero el MODIFY y el
 * ADD CONSTRAINT posteriores no — en producción, el entrypoint de Railway
 * corre `php artisan migrate --force` en cada arranque de contenedor y
 * absorbe el fallo con un simple "ADVERTENCIA" en el log (no frena el
 * deploy), pero Laravel SÍ frena el resto del lote de migraciones al
 * toparse con una excepción acá — la migración correctiva de las 13:00
 * nunca llegó a correr por este motivo. Cada paso queda con su propio
 * try/catch + report() para que el lote pueda seguir de largo pase lo que
 * pase acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setNullable(true);
    }

    public function down(): void
    {
        DB::table('work_orders')->whereNull('retread_shop_id')->delete();
        $this->setNullable(false);
    }

    private function setNullable(bool $nullable): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $fk = 'work_orders_retread_shop_id_foreign';

        if ($driver !== 'mysql') {
            try {
                Schema::table('work_orders', function (Blueprint $table) use ($nullable) {
                    $table->unsignedBigInteger('retread_shop_id')->nullable($nullable)->change();
                });
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        try {
            Schema::table('work_orders', function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk);
            });
        } catch (\Throwable $e) {
            // No existía con ese nombre, o ya se había soltado en una corrida anterior.
            report($e);
        }

        try {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED {$nullSql}");
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->foreign('retread_shop_id')->references('id')->on('retread_shops')->restrictOnDelete();
            });
        } catch (\Throwable $e) {
            // Puede ya existir si el DROP de arriba no encontró nada que soltar. No cortar el lote por esto.
            report($e);
        }
    }
};
