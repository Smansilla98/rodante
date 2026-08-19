<?php

namespace Database\Seeders;

use App\Models\Base;
use App\Models\Company;
use App\Models\Fleet;
use App\Models\MeasurementZone;
use App\Models\MovementReason;
use App\Models\Supplier;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitPosition;
use App\Models\UnitType;
use App\Support\TireProductCatalog;
use App\Support\UnitConfigurationCatalog;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Fleet::query()->exists()) {
            return;
        }

        $company = Company::demo();
        $sanLorenzo = Base::create(['company_id' => $company->id, 'name' => 'Predio MRT San Lorenzo', 'code' => 'SLT', 'location' => 'San Lorenzo, Santa Fe']);
        $rosario = Base::create(['company_id' => $company->id, 'name' => 'Base Rosario', 'code' => 'ROS', 'location' => 'Rosario, Santa Fe']);

        $combustible = Fleet::create(['company_id' => $company->id, 'name' => 'Axion Combustible', 'code' => 'AXN-COMB']);
        $alcohol = Fleet::create(['company_id' => $company->id, 'name' => 'Axion Alcohol', 'code' => 'AXN-ALC']);
        $bio = Fleet::create(['company_id' => $company->id, 'name' => 'Axion Bio', 'code' => 'AXN-BIO']);
        $combustible->bases()->sync([$sanLorenzo->id, $rosario->id]);
        $alcohol->bases()->sync([$sanLorenzo->id]);
        $bio->bases()->sync([$sanLorenzo->id]);

        Supplier::create(['company_id' => $company->id, 'name' => 'Michelin Argentina', 'tax_id' => '30-50000001-9']);
        Supplier::create(['company_id' => $company->id, 'name' => 'Pirelli Neumáticos', 'tax_id' => '30-50000002-7']);
        Supplier::create(['company_id' => $company->id, 'name' => 'Fate SAICI', 'tax_id' => '30-50000003-5']);

        $sizes = [
            ['code' => '275/80 R22.5', 'alias' => null, 'width_mm' => 275, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '295/80 R22.5', 'alias' => null, 'width_mm' => 295, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '315/80 R22.5', 'alias' => null, 'width_mm' => 315, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '385/65 R22.5', 'alias' => 'Gomón lineal', 'width_mm' => 385, 'aspect_ratio' => 65, 'rim_inches' => 22.5],
            ['code' => '385/90 R22.5', 'alias' => 'Gomón', 'width_mm' => 385, 'aspect_ratio' => 90, 'rim_inches' => 22.5],
        ];
        foreach ($sizes as $sizeData) {
            $size = TireSize::firstOrCreate(
                ['code' => $sizeData['code']],
                $sizeData + ['uneven_wear_threshold_mm' => 3]
            );
            if ($size->zones()->doesntExist()) {
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
        }

        TireProductCatalog::sync();

        foreach (UnitConfigurationCatalog::unitTypes() as $type) {
            UnitType::create($type + ['is_active' => true]);
        }

        foreach (UnitConfigurationCatalog::powered() as $layout) {
            $this->seedLayout($layout, 'POWERED', ['TRACTOR', 'CAMION']);
        }
        foreach (UnitConfigurationCatalog::trailers() as $layout) {
            $this->seedLayout($layout, 'TRAILER', $layout['compatible_types']);
        }

        foreach ([
            ['DESGASTE', 'Desgaste', 'BAJA'],
            ['DANO_IRREPARABLE', 'Daño irreparable', 'BAJA'],
            ['SOPLADURA', 'Sopladura', 'BAJA'],
            ['ROTURA', 'Rotura', 'BAJA'],
            ['FIN_DE_VIDA', 'Fin de vida', 'BAJA'],
            ['OTRO_BAJA', 'Otro', 'BAJA'],
            ['ROTACION', 'Rotación / desgaste', 'RETIRO'],
            ['RECAMBIO', 'Cambio / recambio', 'RETIRO'],
            ['PINCHADURA', 'Pinchadura', 'RETIRO'],
            ['CAMBIO_UNIDAD', 'Cambio de unidad', 'RETIRO'],
            ['RECAPADO', 'Envío a recapar', 'RETIRO'],
            ['INSPECCION', 'Inspección', 'RETIRO'],
        ] as [$code, $name, $applies]) {
            MovementReason::create(['code' => $code, 'name' => $name, 'applies_to' => $applies]);
        }
    }

    private function seedLayout(array $layout, string $appliesTo, array $compatibleTypes): void
    {
        $config = UnitConfiguration::create([
            'code' => $layout['code'],
            'name' => $layout['name'],
            'family_code' => $layout['family_code'] ?? $layout['code'],
            'applies_to' => $appliesTo,
            'compatible_types' => $compatibleTypes,
            'description' => $layout['description'],
            'axle_count' => count($layout['axles']),
            'drive_axle_count' => $layout['drive_axle_count'],
            'position_count' => UnitConfigurationCatalog::positionCount($layout),
        ]);

        foreach (UnitConfigurationCatalog::positionRows($layout) as $row) {
            UnitPosition::create($row + ['unit_configuration_id' => $config->id]);
        }
    }
}
