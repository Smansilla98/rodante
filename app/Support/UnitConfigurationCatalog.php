<?php

namespace App\Support;

class UnitConfigurationCatalog
{
    public static function powered(): array
    {
        return [
            [
                'code' => '4X2',
                'name' => '4x2',
                'description' => '2 ejes, 1 motriz. Tractor liviano / camión.',
                'drive_axle_count' => 1,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '4X4',
                'name' => '4x4',
                'description' => '2 ejes, ambos motrices. Off-road.',
                'drive_axle_count' => 2,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '6X2',
                'name' => '6x2',
                'description' => '3 ejes, 1 motriz. Eje 2 tracción dual y eje 3 loca / elevable (tag).',
                'drive_axle_count' => 1,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    3 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE', 'liftable' => true],
                ],
            ],
            [
                'code' => '6X2-P',
                'name' => '6x2 empujador',
                'family_code' => '6X2',
                'description' => '3 ejes, 1 motriz. Eje 2 loca / elevable (pusher) y eje 3 tracción dual.',
                'drive_axle_count' => 1,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE', 'liftable' => true],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '6X4',
                'name' => '6x4',
                'description' => '3 ejes, 2 motrices. Tractor pesado de larga distancia.',
                'drive_axle_count' => 2,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '6X6',
                'name' => '6x6',
                'description' => '3 ejes, todos motrices. Off-road / minería.',
                'drive_axle_count' => 3,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '8X2',
                'name' => '8x2',
                'description' => '4 ejes, 1 motriz. Camión pesado.',
                'drive_axle_count' => 1,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE', 'liftable' => true],
                    3 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE'],
                    4 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '8X4',
                'name' => '8x4',
                'description' => '4 ejes, 2 motrices. Camión pesado / construcción. Dos ejes de dirección.',
                'drive_axle_count' => 2,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    4 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '8X8',
                'name' => '8x8',
                'description' => '4 ejes, todos motrices. Off-road / aplicaciones especiales.',
                'drive_axle_count' => 4,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    4 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '10X4',
                'name' => '10x4',
                'description' => '5 ejes, 2 motrices. Aplicaciones pesadas.',
                'drive_axle_count' => 2,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    3 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE', 'liftable' => true],
                    4 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    5 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
            [
                'code' => '10X6',
                'name' => '10x6',
                'description' => '5 ejes, 3 motrices. Aplicaciones especiales.',
                'drive_axle_count' => 3,
                'axles' => [
                    1 => ['role' => 'DIRECCION', 'wheels' => 'SIMPLE'],
                    2 => ['role' => 'ARRASTRE', 'wheels' => 'SIMPLE', 'liftable' => true],
                    3 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    4 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                    5 => ['role' => 'TRACCION', 'wheels' => 'DUAL'],
                ],
            ],
        ];
    }

    public static function trailers(): array
    {
        $items = [];
        foreach ([1, 2, 3, 4, 5] as $axles) {
            foreach (['SIMPLE', 'DUAL'] as $wheels) {
                $suffix = $wheels === 'DUAL' ? 'D' : 'S';
                $wheelLabel = $wheels === 'DUAL' ? 'dual (mellizas)' : 'lineal';
                $axleMap = [];
                for ($n = 1; $n <= $axles; $n++) {
                    $axleMap[$n] = ['role' => 'ARRASTRE', 'wheels' => $wheels];
                }
                $items[] = [
                    'code' => $axles.'E-'.$suffix,
                    'name' => $axles.' '.($axles === 1 ? 'eje' : 'ejes').' · '.$wheelLabel,
                    'description' => 'Tanque, semirremolque o batea. '.$axles.' '.($axles === 1 ? 'eje' : 'ejes').', '.$wheelLabel.'. Lineal: una cubierta por lado, 295 o 385 según la unidad.',
                    'drive_axle_count' => 0,
                    'compatible_types' => $axles === 1 || $axles === 5
                        ? ['SEMIRREMOLQUE']
                        : ['SEMIRREMOLQUE', 'TANQUE', 'BATEA'],
                    'axles' => $axleMap,
                ];
            }
        }

        $items[] = [
            'code' => '3E-1S2D',
            'name' => '3 ejes · 1 simple + 2 dual',
            'description' => 'Eje 1 simple (direccional o elevable) y ejes 2–3 duales. Típico en tanque.',
            'drive_axle_count' => 0,
            'compatible_types' => ['SEMIRREMOLQUE', 'TANQUE', 'BATEA'],
            'axles' => [
                1 => ['role' => 'DIRECCIONAL', 'wheels' => 'SIMPLE', 'liftable' => true, 'self_steer' => true],
                2 => ['role' => 'ARRASTRE', 'wheels' => 'DUAL'],
                3 => ['role' => 'ARRASTRE', 'wheels' => 'DUAL'],
            ],
        ];

        return $items;
    }

    public static function unitTypes(): array
    {
        return [
            ['code' => 'TRACTOR', 'name' => 'Tractor', 'has_odometer' => true],
            ['code' => 'CAMION', 'name' => 'Camión', 'has_odometer' => true],
            ['code' => 'SEMIRREMOLQUE', 'name' => 'Semirremolque', 'has_odometer' => false],
            ['code' => 'TANQUE', 'name' => 'Tanque', 'has_odometer' => false],
            ['code' => 'BATEA', 'name' => 'Batea', 'has_odometer' => false],
        ];
    }

    public static function poweredByCode(string $code): ?array
    {
        foreach (self::powered() as $layout) {
            if ($layout['code'] === $code) {
                return $layout;
            }
        }

        return null;
    }

    public static function positionCount(array $layout): int
    {
        $slots = 0;
        foreach ($layout['axles'] as $axle) {
            $slots += $axle['wheels'] === 'DUAL' ? 4 : 2;
        }

        return $slots + 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function positionRows(array $layout): array
    {
        $rows = [];
        $sort = 1;
        $axles = $layout['axles'];

        foreach ($axles as $axleNumber => $axle) {
            foreach (self::slotsFor($axle['wheels'], $axle['role'], (int) $axleNumber) as [$suffix, $label, $side, $dual, $col]) {
                $rows[] = [
                    'code' => 'E'.$axleNumber.'_'.$suffix,
                    'name' => 'Eje '.$axleNumber.' — '.$label,
                    'axle_number' => (int) $axleNumber,
                    'axle_role' => $axle['role'],
                    'side' => $side,
                    'dual' => $dual,
                    'is_spare' => false,
                    'is_liftable' => (bool) ($axle['liftable'] ?? false),
                    'is_self_steer' => (bool) ($axle['self_steer'] ?? false),
                    'grid_row' => (int) $axleNumber,
                    'grid_col' => $col,
                    'sort_order' => $sort++,
                ];
            }
        }

        $rows[] = [
            'code' => 'AUXILIO',
            'name' => 'Auxilio',
            'axle_number' => 0,
            'axle_role' => 'AUXILIO',
            'side' => 'CENTRO',
            'dual' => null,
            'is_spare' => true,
            'is_liftable' => false,
            'is_self_steer' => false,
            'grid_row' => count($axles) + 1,
            'grid_col' => 2,
            'sort_order' => $sort,
        ];

        return $rows;
    }

    /**
     * @return list<array{0:string,1:string,2:string,3:?string,4:int}>
     */
    private static function slotsFor(string $wheels, string $role, int $axle): array
    {
        if ($wheels === 'SIMPLE') {
            $left = $role === 'DIRECCION' && $axle === 1 ? 'Delantero izquierdo' : ($role === 'ARRASTRE' ? 'Lineal izquierdo' : 'Izquierdo');
            $right = $role === 'DIRECCION' && $axle === 1 ? 'Delantero derecho' : ($role === 'ARRASTRE' ? 'Lineal derecho' : 'Derecho');

            return [
                ['IZQ', $left, 'IZQ', null, 0],
                ['DER', $right, 'DER', null, 4],
            ];
        }

        return [
            ['IZQ_EXT', 'Izquierdo exterior', 'IZQ', 'EXT', 0],
            ['IZQ_INT', 'Izquierdo interior', 'IZQ', 'INT', 1],
            ['DER_INT', 'Derecho interior', 'DER', 'INT', 3],
            ['DER_EXT', 'Derecho exterior', 'DER', 'EXT', 4],
        ];
    }
}
