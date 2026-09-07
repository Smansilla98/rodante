<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 1/3 multiempresa: agrega company_id nullable a tablas operativas y de catálogo.
 * El backfill y el NOT NULL van en migraciones posteriores.
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
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('company_id')
                    ->nullable()
                    ->constrained()
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('company_id');
            });
        }
    }
};
