<?php

use App\Models\UnitConfiguration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (UnitConfiguration::where('applies_to', 'TRAILER')->get() as $cfg) {
            if (! str_ends_with((string) $cfg->code, '-S')) {
                continue;
            }
            foreach ($cfg->positions()->where('axle_role', 'ARRASTRE')->whereNull('dual')->where('is_spare', false)->get() as $position) {
                if ($position->side === 'IZQ') {
                    $position->update(['name' => 'Eje '.$position->axle_number.' — Lineal izquierdo']);
                }
                if ($position->side === 'DER') {
                    $position->update(['name' => 'Eje '.$position->axle_number.' — Lineal derecho']);
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
