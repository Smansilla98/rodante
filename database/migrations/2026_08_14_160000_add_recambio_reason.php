<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('movement_reasons')->where('code', 'PINCHADURA')->exists()) {
            return;
        }

        if (! DB::table('movement_reasons')->where('code', 'RECAMBIO')->exists()) {
            DB::table('movement_reasons')->insert([
                'code' => 'RECAMBIO',
                'name' => 'Cambio / recambio',
                'applies_to' => 'RETIRO',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('movement_reasons')->where('code', 'RECAMBIO')->delete();
    }
};
