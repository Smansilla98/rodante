<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tire_assignments')
            ->whereNull('ended_at')
            ->whereNull('open_tire_id')
            ->update([
                'open_tire_id' => DB::raw('tire_id'),
                'open_key' => DB::raw('COALESCE(open_key, tire_id)'),
            ]);

        DB::table('tire_assignments')
            ->whereNotNull('ended_at')
            ->where(function ($q) {
                $q->whereNotNull('open_tire_id')->orWhereNotNull('open_key');
            })
            ->update(['open_tire_id' => null, 'open_key' => null]);

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropCheckIfExists('tire_assignments', 'chk_assignment_open_consistency');
        DB::statement(<<<'SQL'
            ALTER TABLE tire_assignments
            ADD CONSTRAINT chk_assignment_open_consistency
            CHECK (
                (ended_at IS NULL AND open_tire_id IS NOT NULL AND open_tire_id = tire_id)
                OR (ended_at IS NOT NULL AND open_tire_id IS NULL AND open_key IS NULL)
            )
        SQL);

        $this->dropCheckIfExists('tire_assignment_segments', 'chk_segment_open_consistency');
        DB::statement(<<<'SQL'
            ALTER TABLE tire_assignment_segments
            ADD CONSTRAINT chk_segment_open_consistency
            CHECK (
                (ended_at IS NULL AND open_assignment_id IS NOT NULL)
                OR (ended_at IS NOT NULL AND open_assignment_id IS NULL AND open_key IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropCheckIfExists('tire_assignments', 'chk_assignment_open_consistency');
        $this->dropCheckIfExists('tire_assignment_segments', 'chk_segment_open_consistency');
    }

    private function dropCheckIfExists(string $table, string $name): void
    {
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'CHECK']
        );
        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
        }
    }
};
