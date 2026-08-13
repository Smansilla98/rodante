<?php

namespace Database\Seeders;

use App\Models\Base;
use App\Models\Fleet;
use App\Models\MeasurementZone;
use App\Models\MovementReason;
use App\Models\Supplier;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitPosition;
use App\Models\UnitType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Fleet::query()->exists()) {
            return;
        }

        $sanLorenzo = Base::create(['name' => 'Predio MRT San Lorenzo', 'code' => 'SLT', 'location' => 'San Lorenzo, Santa Fe']);
        $rosario = Base::create(['name' => 'Base Rosario', 'code' => 'ROS', 'location' => 'Rosario, Santa Fe']);

        $combustible = Fleet::create(['name' => 'Axion Combustible', 'code' => 'AXN-COMB']);
        $alcohol = Fleet::create(['name' => 'Axion Alcohol', 'code' => 'AXN-ALC']);
        $bio = Fleet::create(['name' => 'Axion Bio', 'code' => 'AXN-BIO']);
        $combustible->bases()->sync([$sanLorenzo->id, $rosario->id]);
        $alcohol->bases()->sync([$sanLorenzo->id]);
        $bio->bases()->sync([$sanLorenzo->id]);

        Supplier::create(['name' => 'Michelin Argentina', 'tax_id' => '30-50000001-9']);
        Supplier::create(['name' => 'Pirelli Neumáticos', 'tax_id' => '30-50000002-7']);
        Supplier::create(['name' => 'Fate SAICI', 'tax_id' => '30-50000003-5']);

        $sizes = [
            ['code' => '295/80 R22.5', 'alias' => null, 'width_mm' => 295, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '315/80 R22.5', 'alias' => null, 'width_mm' => 315, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '275/80 R22.5', 'alias' => null, 'width_mm' => 275, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '385/90 R22.5', 'alias' => 'Gomón', 'width_mm' => 385, 'aspect_ratio' => 90, 'rim_inches' => 22.5],
        ];
        foreach ($sizes as $sizeData) {
            $size = TireSize::create($sizeData + ['uneven_wear_threshold_mm' => 3]);
            foreach ([
                ['FLANCO_IZQ', 'Flanco izquierdo', 1],
                ['FLANCO_DER', 'Flanco derecho', 2],
                ['CENTRAL', 'Central', 3],
                ['PERIMETRAL', 'Alrededor', 4],
            ] as [$code, $name, $order]) {
                MeasurementZone::create([
                    'tire_size_id' => $size->id,
                    'code' => $code,
                    'name' => $name,
                    'sort_order' => $order,
                ]);
            }
        }

        $catalog = [
            'Pirelli' => [
                ['FH:01', 'Dirección larga distancia'],
                ['TR:01', 'Tracción larga distancia'],
                ['FH:85', 'Dirección regional'],
                ['TR:85', 'Tracción regional'],
            ],
            'Michelin' => [
                ['XZE2+', 'Dirección'],
                ['X Multi D', 'Tracción'],
                ['XZY3', 'Mixta on/off'],
            ],
            'Goodyear' => [
                ['KMAX S', 'Dirección'],
                ['KMAX D', 'Tracción'],
            ],
            'Bridgestone' => [
                ['R249', 'Dirección'],
                ['M729', 'Tracción'],
            ],
            'Fate' => [
                ['DR-500', 'Dirección nacional'],
                ['AR-440', 'Tracción nacional'],
            ],
            'Continental' => [
                ['HSR2', 'Dirección'],
                ['HDR2', 'Tracción'],
            ],
            'Kumho' => [
                ['KMA31', 'Dirección'],
            ],
        ];

        $sizeIds = TireSize::pluck('id');
        foreach ($catalog as $brandName => $models) {
            $brand = TireBrand::create(['name' => $brandName]);
            foreach ($models as [$code, $name]) {
                $model = TireModel::create([
                    'tire_brand_id' => $brand->id,
                    'code' => $code,
                    'name' => $name,
                ]);
                $model->sizes()->sync($sizeIds);
            }
        }

        UnitType::insert([
            ['code' => 'CAMION_TRACTOR', 'name' => 'Camión / Tractor', 'has_odometer' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SEMIRREMOLQUE', 'name' => 'Semirremolque', 'has_odometer' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'TANQUE', 'name' => 'Tanque', 'has_odometer' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BATEA', 'name' => 'Batea', 'has_odometer' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seedSixByTwentyFour('6X24-T', '6X24 Tractor', 'TRACTOR', 3);
        $this->seedSixByTwentyFour('6X24-A', '6X24 Acoplado', 'ACOPLADO', 3);
        $this->seedSixByOne();

        foreach ([
            ['DESGASTE', 'Desgaste', 'BAJA'],
            ['DANO_IRREPARABLE', 'Daño irreparable', 'BAJA'],
            ['SOPLADURA', 'Sopladura', 'BAJA'],
            ['ROTURA', 'Rotura', 'BAJA'],
            ['FIN_DE_VIDA', 'Fin de vida', 'BAJA'],
            ['OTRO_BAJA', 'Otro', 'BAJA'],
            ['ROTACION', 'Rotación / desgaste', 'RETIRO'],
            ['PINCHADURA', 'Pinchadura', 'RETIRO'],
            ['CAMBIO_UNIDAD', 'Cambio de unidad', 'RETIRO'],
            ['RECAPADO', 'Envío a recapar', 'RETIRO'],
            ['INSPECCION', 'Inspección', 'RETIRO'],
        ] as [$code, $name, $applies]) {
            MovementReason::create(['code' => $code, 'name' => $name, 'applies_to' => $applies]);
        }
    }

    private function seedSixByTwentyFour(string $code, string $name, string $applies, int $axles): void
    {
        $steerFirst = $applies === 'TRACTOR';
        $config = UnitConfiguration::create([
            'code' => $code,
            'name' => $name,
            'family_code' => '6X24',
            'applies_to' => $applies,
            'axle_count' => $axles,
            'position_count' => ($steerFirst ? 2 + (($axles - 1) * 4) : $axles * 4) + 1,
        ]);

        $sort = 1;
        for ($axle = 1; $axle <= $axles; $axle++) {
            $slots = $steerFirst && $axle === 1
                ? [
                    ['IZQ', 'Izquierdo', 'IZQ', null, 0],
                    ['DER', 'Derecho', 'DER', null, 4],
                ]
                : [
                    ['IZQ_EXT', 'Izquierdo exterior', 'IZQ', 'EXT', 0],
                    ['IZQ_INT', 'Izquierdo interior', 'IZQ', 'INT', 1],
                    ['DER_INT', 'Derecho interior', 'DER', 'INT', 3],
                    ['DER_EXT', 'Derecho exterior', 'DER', 'EXT', 4],
                ];

            foreach ($slots as [$suffix, $label, $side, $dual, $col]) {
                UnitPosition::create([
                    'unit_configuration_id' => $config->id,
                    'code' => "E{$axle}_{$suffix}",
                    'name' => "Eje {$axle} {$label}",
                    'axle_number' => $axle,
                    'side' => $side,
                    'dual' => $dual,
                    'is_spare' => false,
                    'grid_row' => $axle,
                    'grid_col' => $col,
                    'sort_order' => $sort++,
                ]);
            }
        }

        UnitPosition::create([
            'unit_configuration_id' => $config->id,
            'code' => 'AUXILIO',
            'name' => 'Auxilio',
            'axle_number' => 0,
            'side' => 'CENTRO',
            'dual' => null,
            'is_spare' => true,
            'grid_row' => $axles + 1,
            'grid_col' => 2,
            'sort_order' => $sort,
        ]);
    }

    private function seedSixByOne(): void
    {
        $config = UnitConfiguration::create([
            'code' => '6X1',
            'name' => '6X1 — un eje, 3 por lado',
            'family_code' => '6X1',
            'applies_to' => 'ANY',
            'axle_count' => 1,
            'position_count' => 7,
        ]);

        $sort = 1;
        foreach ([
            ['IZQ_EXT', 'Izquierdo exterior', 'IZQ', 'EXT', 0],
            ['IZQ_MED', 'Izquierdo medio', 'IZQ', 'MED', 1],
            ['IZQ_INT', 'Izquierdo interior', 'IZQ', 'INT', 2],
            ['DER_INT', 'Derecho interior', 'DER', 'INT', 4],
            ['DER_MED', 'Derecho medio', 'DER', 'MED', 5],
            ['DER_EXT', 'Derecho exterior', 'DER', 'EXT', 6],
        ] as [$suffix, $label, $side, $dual, $col]) {
            UnitPosition::create([
                'unit_configuration_id' => $config->id,
                'code' => 'E1_'.$suffix,
                'name' => 'Eje 1 '.$label,
                'axle_number' => 1,
                'side' => $side,
                'dual' => $dual,
                'is_spare' => false,
                'grid_row' => 1,
                'grid_col' => $col,
                'sort_order' => $sort++,
            ]);
        }

        UnitPosition::create([
            'unit_configuration_id' => $config->id,
            'code' => 'AUXILIO',
            'name' => 'Auxilio',
            'axle_number' => 0,
            'side' => 'CENTRO',
            'is_spare' => true,
            'grid_row' => 2,
            'grid_col' => 3,
            'sort_order' => $sort,
        ]);
    }
}
