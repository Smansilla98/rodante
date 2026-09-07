<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 3/3: company_id NOT NULL tras backfill completo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'tire_brands',
        'tire_sizes',
        'unit_types',
        'unit_configurations',
        'movement_reasons',
        'tire_models',
        'tire_model_sizes',
        'measurement_zones',
        'unit_positions',
        'tire_purchase_items',
        'tire_lifecycles',
        'tire_current_locations',
        'tire_assignments',
        'tire_assignment_segments',
        'tire_movements',
        'tire_operations',
        'odometer_readings',
        'tire_incidents',
        'tire_measurements',
        'tire_measurement_readings',
        'unit_couplings',
        'unit_configuration_changes',
        'tire_number_changes',
        'inventory_lines',
        'work_order_items',
    ];

    public function up(): void
    {
        $fallback = DB::table('companies')->orderBy('id')->value('id');
        $driver = Schema::getConnection()->getDriverName();

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            if ($fallback) {
                $left = DB::table($table)->whereNull('company_id')->update(['company_id' => $fallback]);
                if ($left > 0) {
                    Log::warning("NOT NULL {$table}: {$left} nulos residuales → company_id={$fallback}.");
                }
            }

            $this->setNullable($table, false, $driver);
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }
            $this->setNullable($table, true, $driver);
        }
    }

    private function setNullable(string $table, bool $nullable, string $driver): void
    {
        $fk = "{$table}_company_id_foreign";

        if ($driver === 'mysql') {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($fk) {
                    $blueprint->dropForeign($fk);
                });
            } catch (\Throwable) {
                // Puede no existir si falló a mitad de camino.
            }

            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` BIGINT UNSIGNED {$nullSql}");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            });

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($nullable) {
            $blueprint->unsignedBigInteger('company_id')->nullable($nullable)->change();
        });
    }
};
