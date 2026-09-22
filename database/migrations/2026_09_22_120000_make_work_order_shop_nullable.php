<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una Orden de Trabajo puede ser interna (reparación/recapado hecho por
 * personal propio en el taller de la empresa, sin recapadora externa).
 * retread_shop_id nulo = orden interna; con valor = taller externo.
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

        if ($driver === 'mysql') {
            try {
                Schema::table('work_orders', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk);
                });
            } catch (\Throwable) {
                // No existía si una corrida previa falló a mitad de camino.
            }

            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED {$nullSql}");

            Schema::table('work_orders', function (Blueprint $table) {
                $table->foreign('retread_shop_id')->references('id')->on('retread_shops')->restrictOnDelete();
            });

            return;
        }

        Schema::table('work_orders', function (Blueprint $table) use ($nullable) {
            $table->unsignedBigInteger('retread_shop_id')->nullable($nullable)->change();
        });
    }
};
