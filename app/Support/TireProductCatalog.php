<?php

namespace App\Support;

use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;

class TireProductCatalog
{
    /**
     * Medidas de plaza que usa la flota. Cada modelo solo se vincula a las que fabrica.
     *
     * @return list<array{code:string,alias:?string,width_mm:int,aspect_ratio:int,rim_inches:float}>
     */
    public static function sizes(): array
    {
        return [
            ['code' => '275/80 R22.5', 'alias' => null, 'width_mm' => 275, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '295/80 R22.5', 'alias' => null, 'width_mm' => 295, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '315/80 R22.5', 'alias' => null, 'width_mm' => 315, 'aspect_ratio' => 80, 'rim_inches' => 22.5],
            ['code' => '385/65 R22.5', 'alias' => 'Gomón lineal', 'width_mm' => 385, 'aspect_ratio' => 65, 'rim_inches' => 22.5],
            ['code' => '385/90 R22.5', 'alias' => 'Gomón', 'width_mm' => 385, 'aspect_ratio' => 90, 'rim_inches' => 22.5],
        ];
    }

    /**
     * Catálogo por marca: el código del diseño es exclusivo de esa marca
     * y las medidas son las publicadas por el fabricante (22.5" de plaza).
     *
     * @return array<string, list<array{code:string,name:string,application:string,sizes:list<string>}>>
     */
    public static function brands(): array
    {
        $s275 = '275/80 R22.5';
        $s295 = '295/80 R22.5';
        $s315 = '315/80 R22.5';
        $s385 = '385/65 R22.5';

        return [
            'Pirelli' => [
                ['code' => 'FH:01', 'name' => 'Dirección larga distancia', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295, $s315, $s385]],
                ['code' => 'TH:01', 'name' => 'Tracción larga distancia', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'TR:01', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'FR:01', 'name' => 'Arrastre / all-position', 'application' => 'ARRASTRE', 'sizes' => [$s295, $s315, $s385]],
                ['code' => 'ST:01', 'name' => 'Arrastre lineal (gomón)', 'application' => 'ARRASTRE', 'sizes' => [$s385]],
                ['code' => 'FH:85', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s295, $s315, $s385]],
                ['code' => 'TR:85', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'FR:85', 'name' => 'Arrastre regional', 'application' => 'ARRASTRE', 'sizes' => [$s295, $s315, $s385]],
            ],
            'Michelin' => [
                ['code' => 'XZE2+', 'name' => 'Dirección / all-position regional', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295, $s315]],
                ['code' => 'X Multi Z', 'name' => 'Dirección / all-position', 'application' => 'DIRECCION', 'sizes' => [$s295, $s315, $s385]],
                ['code' => 'X Multi D', 'name' => 'Tracción', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'X Multi T', 'name' => 'Arrastre lineal', 'application' => 'ARRASTRE', 'sizes' => [$s385]],
                ['code' => 'XZY3', 'name' => 'Mixta on/off', 'application' => 'MIXTO', 'sizes' => [$s275, $s295, $s315, $s385]],
            ],
            'Goodyear' => [
                ['code' => 'KMAX S', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s295, $s315]],
                ['code' => 'KMAX D', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'KMAX T', 'name' => 'Arrastre', 'application' => 'ARRASTRE', 'sizes' => [$s295, $s315, $s385]],
            ],
            'Bridgestone' => [
                ['code' => 'R249', 'name' => 'Dirección / all-position', 'application' => 'DIRECCION', 'sizes' => [$s295, $s315]],
                ['code' => 'R268', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295, $s315]],
                ['code' => 'M729', 'name' => 'Tracción', 'application' => 'TRACCION', 'sizes' => [$s275, $s295, $s315]],
                ['code' => 'R168', 'name' => 'Arrastre lineal', 'application' => 'ARRASTRE', 'sizes' => [$s295, $s385]],
            ],
            'Fate' => [
                ['code' => 'SR-200', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295, $s315, $s385]],
                ['code' => 'SR-210', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s295]],
                ['code' => 'SR-260', 'name' => 'Dirección regional', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295]],
                ['code' => 'DR-400', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s275, $s295, $s315]],
                ['code' => 'DR-410', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295]],
                ['code' => 'DR-460', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s275, $s295]],
                ['code' => 'SC-240', 'name' => 'Dirección mixta', 'application' => 'MIXTO', 'sizes' => [$s275, $s295]],
                ['code' => 'DC-480', 'name' => 'Tracción mixta', 'application' => 'TRACCION', 'sizes' => [$s275, $s295]],
                ['code' => 'TR-500', 'name' => 'Arrastre lineal (gomón)', 'application' => 'ARRASTRE', 'sizes' => [$s385]],
            ],
            'Continental' => [
                ['code' => 'HSR2', 'name' => 'Dirección / all-position', 'application' => 'DIRECCION', 'sizes' => [$s275, $s295, $s315, $s385]],
                ['code' => 'HDR2', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295, $s315]],
                ['code' => 'HTR2', 'name' => 'Arrastre', 'application' => 'ARRASTRE', 'sizes' => [$s295, $s385]],
            ],
            'Kumho' => [
                ['code' => 'KMA31', 'name' => 'Dirección / all-position', 'application' => 'DIRECCION', 'sizes' => [$s295, $s315]],
                ['code' => 'KRD50', 'name' => 'Tracción regional', 'application' => 'TRACCION', 'sizes' => [$s295]],
            ],
        ];
    }

    public static function sync(): void
    {
        self::renameLegacyCodes();

        foreach (self::brands() as $brandName => $models) {
            $brand = TireBrand::firstOrCreate(
                ['name' => $brandName],
                ['is_active' => true]
            );
            $keep = [];
            foreach ($models as $row) {
                $keep[] = $row['code'];
                $model = TireModel::updateOrCreate(
                    ['tire_brand_id' => $brand->id, 'code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'application' => $row['application'],
                        'is_active' => true,
                    ]
                );
                $model->sizes()->sync(TireSize::whereIn('code', $row['sizes'])->pluck('id'));
            }

            $brand->models()
                ->whereNotIn('code', $keep)
                ->whereDoesntHave('tires')
                ->update(['is_active' => false]);
        }
    }

    /**
     * @return array{brands: list<array{id:int,name:string,models:list<array{id:int,code:string,label:string,size_ids:list<int>}}>, sizes: list<array{id:int,label:string}>}
     */
    public static function uiPayload(): array
    {
        return [
            'brands' => TireBrand::query()
                ->where('is_active', true)
                ->with(['models' => fn ($q) => $q->where('is_active', true)->with('sizes')->orderBy('code')])
                ->orderBy('name')
                ->get()
                ->map(fn (TireBrand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'models' => $brand->models->map(fn (TireModel $model) => [
                        'id' => $model->id,
                        'code' => $model->code,
                        'label' => $model->code.' — '.$model->name,
                        'size_ids' => $model->sizes->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'sizes' => TireSize::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (TireSize $size) => [
                    'id' => $size->id,
                    'label' => $size->displayName(),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function renameLegacyCodes(): void
    {
        $fate = TireBrand::where('name', 'Fate')->first();
        if (! $fate) {
            return;
        }

        $legacyDrive = TireModel::where('tire_brand_id', $fate->id)->where('code', 'DR-500')->first();
        $canonicalDrive = TireModel::where('tire_brand_id', $fate->id)->where('code', 'DR-400')->first();
        if ($legacyDrive && ! $canonicalDrive) {
            $legacyDrive->update(['code' => 'DR-400', 'name' => 'Tracción regional', 'application' => 'TRACCION']);
        }

        $legacyTrailer = TireModel::where('tire_brand_id', $fate->id)->where('code', 'AR-440')->first();
        $canonicalTrailer = TireModel::where('tire_brand_id', $fate->id)->where('code', 'TR-500')->first();
        if ($legacyTrailer && ! $canonicalTrailer) {
            $legacyTrailer->update(['code' => 'TR-500', 'name' => 'Arrastre lineal (gomón)', 'application' => 'ARRASTRE']);
        }
    }
}
