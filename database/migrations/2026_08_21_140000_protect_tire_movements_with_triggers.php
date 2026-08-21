<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS tire_movements_prevent_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER tire_movements_prevent_update
            BEFORE UPDATE ON tire_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'tire_movements is immutable: UPDATE is forbidden'
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS tire_movements_prevent_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER tire_movements_prevent_delete
            BEFORE DELETE ON tire_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'tire_movements is immutable: DELETE is forbidden'
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS tire_movements_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS tire_movements_prevent_delete');
    }
};
