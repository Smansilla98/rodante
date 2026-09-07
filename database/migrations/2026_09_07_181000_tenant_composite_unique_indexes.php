<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uniques globales → compuestos con company_id (multiempresa).
 * Idempotente: tolera corridas parciales fallidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rekey('users', 'username', ['company_id', 'username']);
        $this->rekey('users', 'email', ['company_id', 'email']);
        $this->rekey('fleets', 'code', ['company_id', 'code']);
        $this->rekey('bases', 'code', ['company_id', 'code']);
        $this->ensureUnique('suppliers', ['company_id', 'name']);
        $this->rekey('fleet_units', 'plate', ['company_id', 'plate']);
        $this->rekey('tire_brands', 'name', ['company_id', 'name']);
        $this->rekey('tire_sizes', 'code', ['company_id', 'code']);
        $this->rekey('unit_types', 'code', ['company_id', 'code']);
        $this->rekey('unit_configurations', 'code', ['company_id', 'code']);
        $this->rekey('movement_reasons', 'code', ['company_id', 'code']);

        if ($this->hasIndex('tire_models', 'tire_models_tire_brand_id_code_unique')) {
            if (! $this->hasIndex('tire_models', 'tire_models_tire_brand_id_index')) {
                Schema::table('tire_models', function (Blueprint $table) {
                    $table->index('tire_brand_id');
                });
            }
            Schema::table('tire_models', function (Blueprint $table) {
                $table->dropUnique(['tire_brand_id', 'code']);
            });
        }
        $this->ensureUnique('tire_models', ['company_id', 'tire_brand_id', 'code']);
    }

    public function down(): void
    {
        if ($this->hasIndex('tire_models', 'tire_models_company_id_tire_brand_id_code_unique')) {
            Schema::table('tire_models', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'tire_brand_id', 'code']);
                $table->unique(['tire_brand_id', 'code']);
            });
            if ($this->hasIndex('tire_models', 'tire_models_tire_brand_id_index')) {
                Schema::table('tire_models', function (Blueprint $table) {
                    $table->dropIndex(['tire_brand_id']);
                });
            }
        }

        $this->rekeyDown('movement_reasons', ['company_id', 'code'], 'code');
        $this->rekeyDown('unit_configurations', ['company_id', 'code'], 'code');
        $this->rekeyDown('unit_types', ['company_id', 'code'], 'code');
        $this->rekeyDown('tire_sizes', ['company_id', 'code'], 'code');
        $this->rekeyDown('tire_brands', ['company_id', 'name'], 'name');
        $this->rekeyDown('fleet_units', ['company_id', 'plate'], 'plate');
        if ($this->hasIndex('suppliers', 'suppliers_company_id_name_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'name']);
            });
        }
        $this->rekeyDown('bases', ['company_id', 'code'], 'code');
        $this->rekeyDown('fleets', ['company_id', 'code'], 'code');
        $this->rekeyDown('users', ['company_id', 'email'], 'email');
        $this->rekeyDown('users', ['company_id', 'username'], 'username');
    }

    /** @param list<string> $composite */
    private function rekey(string $table, string $oldColumn, array $composite): void
    {
        $oldName = $table.'_'.$oldColumn.'_unique';
        if ($this->hasIndex($table, $oldName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldColumn) {
                $blueprint->dropUnique([$oldColumn]);
            });
        }
        $this->ensureUnique($table, $composite);
    }

    /** @param list<string> $composite */
    private function rekeyDown(string $table, array $composite, string $oldColumn): void
    {
        $compositeName = $table.'_'.implode('_', $composite).'_unique';
        if ($this->hasIndex($table, $compositeName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($composite) {
                $blueprint->dropUnique($composite);
            });
        }
        $oldName = $table.'_'.$oldColumn.'_unique';
        if (! $this->hasIndex($table, $oldName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldColumn) {
                $blueprint->unique($oldColumn);
            });
        }
    }

    /** @param list<string> $columns */
    private function ensureUnique(string $table, array $columns): void
    {
        $name = $table.'_'.implode('_', $columns).'_unique';
        if ($this->hasIndex($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->unique($columns);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? '') === $name) {
                    return true;
                }
            }

            return false;
        }

        $schema = Schema::getConnection()->getDatabaseName();
        $found = DB::table('information_schema.statistics')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();

        return $found;
    }
};
