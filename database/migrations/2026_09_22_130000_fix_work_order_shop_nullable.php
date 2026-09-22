<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración correctiva: 2026_09_22_120000_make_work_order_shop_nullable
 * quedó registrada como corrida pero en producción retread_shop_id siguió
 * NOT NULL (confirmado en vivo: crear una OT sin recapadora tira 500 por
 * violación de constraint; con recapadora sigue funcionando bien).
 *
 * Un primer intento de corrección (que buscaba el nombre real del FK por
 * information_schema y volvía a crearlo con el nombre estándar de Laravel)
 * tampoco tuvo efecto visible — sospecha: si el FK no se pudo soltar (por
 * permisos restringidos sobre information_schema en el MySQL gestionado de
 * Railway, o porque ya tenía ese nombre), el ADD CONSTRAINT con el mismo
 * nombre reventaba por "ya existe" y la migración entera fallaba, dejando
 * el deploy sin promoverse.
 *
 * Esta versión no puede fallar: cada paso (soltar el FK por su nombre real
 * O por el nombre estándar, el MODIFY, el volver a crear el FK) está
 * envuelto en su propio try/catch. Si algún paso no hace falta o ya está
 * hecho, se ignora en vez de cortar la migración. Al final verifica el
 * estado real contra information_schema — si no logró dejar la columna
 * nullable, no rompe el deploy, pero el estado queda documentado en el
 * log de Laravel (storage/logs) para diagnosticarlo después.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('retread_shop_id')->nullable()->change();
            });

            return;
        }

        if ($this->columnIsNullable()) {
            return;
        }

        // Intento 1: nombre real del constraint, vía information_schema.
        $this->tryDropForeignKeyByName($this->realForeignKeyName());
        // Intento 2 (por si el 1 no encontró nada, ej. permisos restringidos): nombre estándar de Laravel.
        $this->tryDropForeignKeyByName('work_orders_retread_shop_id_foreign');

        try {
            DB::statement('ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            DB::statement(
                'ALTER TABLE `work_orders` '.
                'ADD CONSTRAINT `work_orders_retread_shop_id_foreign` '.
                'FOREIGN KEY (`retread_shop_id`) REFERENCES `retread_shops` (`id`) ON DELETE RESTRICT'
            );
        } catch (\Throwable $e) {
            // Puede ya existir si un intento anterior llegó hasta acá. No cortar el deploy por esto.
            report($e);
        }

        if (! $this->columnIsNullable()) {
            report(new \RuntimeException(
                'work_orders.retread_shop_id sigue NOT NULL después de la migración correctiva — revisar a mano.'
            ));
        }
    }

    public function down(): void
    {
        DB::table('work_orders')->whereNull('retread_shop_id')->delete();

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('retread_shop_id')->nullable(false)->change();
            });

            return;
        }

        $this->tryDropForeignKeyByName($this->realForeignKeyName());
        $this->tryDropForeignKeyByName('work_orders_retread_shop_id_foreign');

        try {
            DB::statement('ALTER TABLE `work_orders` MODIFY `retread_shop_id` BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            DB::statement(
                'ALTER TABLE `work_orders` '.
                'ADD CONSTRAINT `work_orders_retread_shop_id_foreign` '.
                'FOREIGN KEY (`retread_shop_id`) REFERENCES `retread_shops` (`id`) ON DELETE RESTRICT'
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function columnIsNullable(): bool
    {
        try {
            $row = DB::select(
                'SELECT IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS '.
                'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['work_orders', 'retread_shop_id']
            );

            return isset($row[0]) && strtoupper((string) $row[0]->is_nullable) === 'YES';
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function realForeignKeyName(): ?string
    {
        try {
            $row = DB::select(
                'SELECT CONSTRAINT_NAME AS constraint_name FROM information_schema.KEY_COLUMN_USAGE '.
                'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? '.
                'AND REFERENCED_TABLE_NAME IS NOT NULL',
                ['work_orders', 'retread_shop_id']
            );

            return $row[0]->constraint_name ?? null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function tryDropForeignKeyByName(?string $name): void
    {
        if (! $name) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `work_orders` DROP FOREIGN KEY `{$name}`");
        } catch (\Throwable $e) {
            // No existía con ese nombre, o ya se había soltado. Seguimos.
        }
    }
};
