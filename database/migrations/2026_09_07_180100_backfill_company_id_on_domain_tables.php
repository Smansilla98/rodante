<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 2/3: backfill masivo de company_id (MySQL JOIN / SQLite subquery).
 * Filas huérfanas → empresa existente + log. No borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fallback = DB::table('companies')->orderBy('id')->value('id');
        if (! $fallback) {
            Log::warning('Backfill company_id: no hay empresas; se omite.');

            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        $this->assignFallback(['tire_brands', 'tire_sizes', 'unit_types', 'unit_configurations', 'movement_reasons'], $fallback);

        $this->bulkBackfill($driver, 'tire_models', 'tire_brands', 'tire_brand_id', $fallback);
        $this->bulkBackfill($driver, 'tire_model_sizes', 'tire_models', 'tire_model_id', $fallback);
        $this->bulkBackfill($driver, 'measurement_zones', 'tire_sizes', 'tire_size_id', $fallback);
        $this->bulkBackfill($driver, 'unit_positions', 'unit_configurations', 'unit_configuration_id', $fallback);

        $this->bulkBackfill($driver, 'tire_purchase_items', 'tire_purchases', 'tire_purchase_id', $fallback);
        $this->bulkBackfill($driver, 'tire_lifecycles', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_current_locations', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_assignments', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_movements', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_incidents', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_measurements', 'tires', 'tire_id', $fallback);
        $this->bulkBackfill($driver, 'tire_number_changes', 'tires', 'tire_id', $fallback);

        $this->bulkBackfill($driver, 'tire_assignment_segments', 'tire_assignments', 'tire_assignment_id', $fallback);
        $this->bulkBackfill($driver, 'tire_measurement_readings', 'tire_measurements', 'tire_measurement_id', $fallback);

        $this->bulkBackfill($driver, 'tire_operations', 'fleet_units', 'unit_id', $fallback);
        $this->bulkBackfill($driver, 'odometer_readings', 'fleet_units', 'unit_id', $fallback);
        $this->bulkBackfill($driver, 'unit_configuration_changes', 'fleet_units', 'unit_id', $fallback);
        $this->bulkBackfill($driver, 'unit_couplings', 'fleet_units', 'tractor_id', $fallback);

        $this->bulkBackfill($driver, 'inventory_lines', 'inventory_sessions', 'inventory_session_id', $fallback);
        $this->bulkBackfill($driver, 'work_order_items', 'work_orders', 'work_order_id', $fallback);
    }

    public function down(): void
    {
        // Intencionalmente vacío: no vaciamos company_id ya poblado.
    }

    /** @param list<string> $tables */
    private function assignFallback(array $tables, int|string $fallback): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }
            $updated = DB::table($table)->whereNull('company_id')->update(['company_id' => $fallback]);
            if ($updated > 0) {
                Log::info("Backfill {$table}: {$updated} filas → company_id={$fallback} (catálogo raíz).");
            }
        }
    }

    private function bulkBackfill(string $driver, string $table, string $parent, string $fk, int|string $fallback): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return;
        }
        if (! Schema::hasTable($parent) || ! Schema::hasColumn($parent, 'company_id')) {
            $this->assignFallback([$table], $fallback);

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("
                UPDATE `{$table}` AS c
                INNER JOIN `{$parent}` AS p ON p.id = c.`{$fk}`
                SET c.company_id = p.company_id
                WHERE c.company_id IS NULL AND p.company_id IS NOT NULL
            ");
        } else {
            DB::statement("
                UPDATE \"{$table}\"
                SET company_id = (
                    SELECT p.company_id FROM \"{$parent}\" AS p
                    WHERE p.id = \"{$table}\".\"{$fk}\"
                )
                WHERE company_id IS NULL
            ");
        }

        $orphans = DB::table($table)->whereNull('company_id')->count();
        if ($orphans > 0) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $fallback]);
            Log::warning("Backfill {$table}: {$orphans} huérfanas → company_id={$fallback}.");
        }
    }
};
