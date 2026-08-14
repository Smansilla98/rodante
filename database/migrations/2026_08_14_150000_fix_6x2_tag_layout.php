<?php

use App\Support\UnitConfigurationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('unit_configurations')->where('code', '6X2')->exists()) {
            return;
        }

        $this->rebuildPowered('6X2');
        $this->ensurePowered('6X2-P');
    }

    public function down(): void
    {
        $pusher = DB::table('unit_configurations')->where('code', '6X2-P')->first();
        if ($pusher) {
            DB::table('unit_positions')->where('unit_configuration_id', $pusher->id)->delete();
            DB::table('unit_configurations')->where('id', $pusher->id)->delete();
        }
    }

    private function rebuildPowered(string $code): void
    {
        $layout = UnitConfigurationCatalog::poweredByCode($code);
        $config = DB::table('unit_configurations')->where('code', $code)->first();
        if (! $layout || ! $config) {
            return;
        }

        $positionIds = DB::table('unit_positions')->where('unit_configuration_id', $config->id)->pluck('id');
        if ($positionIds->isNotEmpty() && DB::table('tire_assignments')->whereIn('start_position_id', $positionIds)->exists()) {
            return;
        }

        DB::table('unit_configurations')->where('id', $config->id)->update([
            'name' => $layout['name'],
            'family_code' => $layout['family_code'] ?? $layout['code'],
            'description' => $layout['description'],
            'axle_count' => count($layout['axles']),
            'drive_axle_count' => $layout['drive_axle_count'],
            'position_count' => UnitConfigurationCatalog::positionCount($layout),
        ]);

        DB::table('unit_positions')->where('unit_configuration_id', $config->id)->delete();
        $this->insertPositions((int) $config->id, $layout);
    }

    private function ensurePowered(string $code): void
    {
        if (DB::table('unit_configurations')->where('code', $code)->exists()) {
            return;
        }

        $layout = UnitConfigurationCatalog::poweredByCode($code);
        if (! $layout) {
            return;
        }

        $id = DB::table('unit_configurations')->insertGetId([
            'code' => $layout['code'],
            'name' => $layout['name'],
            'family_code' => $layout['family_code'] ?? $layout['code'],
            'applies_to' => 'POWERED',
            'compatible_types' => json_encode(['TRACTOR', 'CAMION']),
            'description' => $layout['description'],
            'axle_count' => count($layout['axles']),
            'drive_axle_count' => $layout['drive_axle_count'],
            'position_count' => UnitConfigurationCatalog::positionCount($layout),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertPositions($id, $layout);
    }

    private function insertPositions(int $configurationId, array $layout): void
    {
        foreach (UnitConfigurationCatalog::positionRows($layout) as $row) {
            DB::table('unit_positions')->insert($row + ['unit_configuration_id' => $configurationId]);
        }
    }
};
