<?php

namespace App\Support;

use App\Enums\UserRole;

class SystemGuide
{
    /**
     * @return list<array{role: UserRole, summary: string, day: string, can: list<string>, cannot: list<string>}>
     */
    public static function roles(): array
    {
        return [
            [
                'role' => UserRole::Consulta,
                'summary' => 'Solo lectura. Sirve para gerencia, auditor o quien necesita ver el estado de la flota sin cargar movimientos.',
                'day' => 'Entra al tablero, abre planillas, ficha de cubiertas, stock, compras y reportes. No confirma nada ni opera el mapa.',
                'can' => [
                    'Ver tablero, unidades, stock, cubiertas, compras y odómetros',
                    'Consultar km, consumo, incidencias y movimientos',
                    'Buscar una cubierta por número',
                ],
                'cannot' => [
                    'Montar, rotar, retirar o medir cubiertas',
                    'Cargar compras ni corregir odómetros',
                    'Acoplar unidades, dar de baja ni administrar catálogos',
                ],
            ],
            [
                'role' => UserRole::Operario,
                'summary' => 'Trabajo de playa y taller: opera la planilla del día sobre tractor y acoplado.',
                'day' => 'Abre la unidad, carga el km, monta o cambia cubiertas, registra pinchadura, rotación, incidencia y medición.',
                'can' => [
                    'Operar la planilla: instalar, cambio, pinchadura, rotación, retirar a stock, incidencia y medición',
                    'Cargar compras en borrador y confirmar ingreso a stock',
                    'Dar de alta unidades nuevas',
                    'Ver reportes y movimientos',
                ],
                'cannot' => [
                    'Corregir lecturas de odómetro ya asentadas',
                    'Acoplar o desacoplar tractor y semi',
                    'Cambiar la configuración de ejes de una unidad',
                    'Dar de baja definitiva o recapar',
                    'Editar catálogos ni usuarios',
                ],
            ],
            [
                'role' => UserRole::Logistica,
                'summary' => 'Opera igual que el operario y además arma el conjunto tractor + acoplado y corrige km.',
                'day' => 'Acopla el tanque o el semi al tractor, controla odómetros y sigue el movimiento de cubiertas entre bases.',
                'can' => [
                    'Toda la operación de planilla y compras',
                    'Acoplar y desacoplar unidades (el acoplado usa el km del tractor)',
                    'Corregir lecturas de odómetro',
                    'Ver reportes y movimientos',
                ],
                'cannot' => [
                    'Cambiar la configuración de la unidad (6X4, 3E, etc.)',
                    'Dar de baja definitiva ni recapar',
                    'Administrar catálogos ni usuarios',
                ],
            ],
            [
                'role' => UserRole::JefeSector,
                'summary' => 'Responsable del sector: decide bajas, recapados y cambios de configuración.',
                'day' => 'Revisa planillas, autoriza retiros definitivos, recapados y el pasaje de una unidad de una configuración a otra.',
                'can' => [
                    'Toda la operación de planilla, compras y acoples',
                    'Corregir odómetros',
                    'Dar de baja una cubierta y registrar recapado (abre vida nueva)',
                    'Cambiar la configuración de ejes (las cubiertas instaladas vuelven a stock)',
                ],
                'cannot' => [
                    'Editar o desactivar catálogos',
                    'Crear o desactivar usuarios',
                    'Editar datos maestros de unidades, cubiertas o compras ya confirmadas',
                ],
            ],
            [
                'role' => UserRole::Administrador,
                'summary' => 'Dueño de la parametrización: catálogos, usuarios y corrección de datos maestros.',
                'day' => 'Mantiene marcas, medidas, flotas, bases, proveedores y usuarios. También opera la flota si hace falta.',
                'can' => [
                    'Todas las operaciones de jefe de sector',
                    'Administrar marcas, modelos, medidas, flotas, bases, proveedores, tipos y motivos',
                    'Alta, edición y desactivación de usuarios',
                    'Editar unidades, cubiertas y compras; desactivar si ya tienen historial',
                ],
                'cannot' => [
                    'Borrar un registro que ya tiene historial (se desactiva, no se elimina)',
                    'Alterar eventos históricos: los movimientos quedan inmutables',
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, name: string, group: string, what: string, route: string|null, cells: array<string, string>}>
     */
    public static function modules(): array
    {
        $v = 'ver';
        $o = 'operar';
        $s = 'si';
        $n = 'no';
        $c = 'corregir';
        $a = 'admin';

        return [
            [
                'key' => 'dashboard',
                'name' => 'Tablero',
                'group' => 'Operación',
                'what' => 'Resumen de cubiertas por estado, stock, km acumulados, próximas a baja y últimas lecturas.',
                'route' => 'dashboard',
                'cells' => self::all($v),
            ],
            [
                'key' => 'units',
                'name' => 'Unidades',
                'group' => 'Operación',
                'what' => 'Listado por patente, flota y acoplado. Desde acá se entra a la planilla.',
                'route' => 'units.index',
                'cells' => self::all($v),
            ],
            [
                'key' => 'sheet',
                'name' => 'Planilla',
                'group' => 'Operación',
                'what' => 'Mapa del chasis: instalar, cambio, pinchadura, rotación, retirar, incidencia y medición. El km se asienta al operar.',
                'route' => 'units.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $o,
                    'LOGISTICA' => $o,
                    'JEFE_SECTOR' => $o,
                    'ADMINISTRADOR' => $o,
                ],
            ],
            [
                'key' => 'couplings',
                'name' => 'Acoplar / desacoplar',
                'group' => 'Operación',
                'what' => 'Une tractor con semi, tanque o batea. El acoplado no tiene odómetro propio: usa el del tractor.',
                'route' => 'units.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $n,
                    'LOGISTICA' => $s,
                    'JEFE_SECTOR' => $s,
                    'ADMINISTRADOR' => $s,
                ],
            ],
            [
                'key' => 'config',
                'name' => 'Cambio de configuración',
                'group' => 'Operación',
                'what' => 'Pasa la unidad de un layout de ejes a otro (por ejemplo urbana a ripio). Las cubiertas instaladas vuelven a stock.',
                'route' => 'units.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $n,
                    'LOGISTICA' => $n,
                    'JEFE_SECTOR' => $s,
                    'ADMINISTRADOR' => $s,
                ],
            ],
            [
                'key' => 'stock',
                'name' => 'Stock',
                'group' => 'Operación',
                'what' => 'Cubiertas listas para instalar. Todo retiro vuelve acá, aunque sea de pasaje.',
                'route' => 'tires.stock',
                'cells' => self::all($v),
            ],
            [
                'key' => 'tires',
                'name' => 'Neumáticos',
                'group' => 'Operación',
                'what' => 'Ficha individual (marca, diseño, número). El historial vive en la cubierta, no en la patente.',
                'route' => 'tires.index',
                'cells' => self::all($v),
            ],
            [
                'key' => 'retire',
                'name' => 'Baja y recapado',
                'group' => 'Operación',
                'what' => 'Baja definitiva o recapado. El recapado cierra la vida actual y abre una nueva. La reparación no.',
                'route' => 'tires.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $n,
                    'LOGISTICA' => $n,
                    'JEFE_SECTOR' => $s,
                    'ADMINISTRADOR' => $s,
                ],
            ],
            [
                'key' => 'purchases',
                'name' => 'Compras',
                'group' => 'Operación',
                'what' => 'Ingreso por número individual consecutivo. El borrador se confirma y las cubiertas entran a stock.',
                'route' => 'purchases.index',
                'cells' => [
                    'CONSULTA' => $v,
                    'OPERARIO' => $o,
                    'LOGISTICA' => $o,
                    'JEFE_SECTOR' => $o,
                    'ADMINISTRADOR' => $a,
                ],
            ],
            [
                'key' => 'odometers',
                'name' => 'Odómetros',
                'group' => 'Operación',
                'what' => 'Lecturas asentadas al operar. Acá se corrige un km mal cargado. El valor no puede bajar respecto de la anterior.',
                'route' => 'odometers.index',
                'cells' => [
                    'CONSULTA' => $v,
                    'OPERARIO' => $v,
                    'LOGISTICA' => $c,
                    'JEFE_SECTOR' => $c,
                    'ADMINISTRADOR' => $c,
                ],
            ],
            [
                'key' => 'km',
                'name' => 'Km por cubierta',
                'group' => 'Consulta',
                'what' => 'Kilómetros acumulados, vidas, recapados y reparaciones de cada cubierta.',
                'route' => 'reports.kilometers',
                'cells' => self::all($v),
            ],
            [
                'key' => 'consumption',
                'name' => 'Consumo',
                'group' => 'Consulta',
                'what' => 'Desgaste y rendimiento para decidir rotación, recapado o baja.',
                'route' => 'reports.consumption',
                'cells' => self::all($v),
            ],
            [
                'key' => 'incidents',
                'name' => 'Incidencias',
                'group' => 'Consulta',
                'what' => 'Pinchaduras, cortes, recapados y demás eventos sobre cubiertas.',
                'route' => 'reports.incidents',
                'cells' => self::all($v),
            ],
            [
                'key' => 'audit',
                'name' => 'Movimientos',
                'group' => 'Consulta',
                'what' => 'Auditoría en lenguaje natural: quién montó, acopló, midió o dio de baja, y cuándo.',
                'route' => 'reports.audit',
                'cells' => self::all($v),
            ],
            [
                'key' => 'catalogs',
                'name' => 'Catálogo',
                'group' => 'Catálogo',
                'what' => 'Marcas, modelos, medidas, flotas, bases, proveedores, tipos y motivos. Si hay historial, se desactiva; no se borra.',
                'route' => 'brands.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $n,
                    'LOGISTICA' => $n,
                    'JEFE_SECTOR' => $n,
                    'ADMINISTRADOR' => $a,
                ],
            ],
            [
                'key' => 'users',
                'name' => 'Usuarios',
                'group' => 'Catálogo',
                'what' => 'Alta de cuentas, rol, flotas y bases habilitadas.',
                'route' => 'users.index',
                'cells' => [
                    'CONSULTA' => $n,
                    'OPERARIO' => $n,
                    'LOGISTICA' => $n,
                    'JEFE_SECTOR' => $n,
                    'ADMINISTRADOR' => $a,
                ],
            ],
        ];
    }

    /**
     * @return array{role: UserRole, summary: string, day: string, can: list<string>, cannot: list<string>}
     */
    public static function forRole(UserRole $role): array
    {
        foreach (self::roles() as $item) {
            if ($item['role'] === $role) {
                return $item;
            }
        }

        return self::roles()[0];
    }

    /**
     * @return list<array{key: string, name: string, group: string, what: string, route: string|null, perm: string, you: bool}>
     */
    public static function modulesFor(UserRole $role): array
    {
        return array_map(function (array $module) use ($role) {
            $perm = $module['cells'][$role->value] ?? 'no';

            return [
                ...$module,
                'perm' => $perm,
                'you' => $perm !== 'no',
            ];
        }, self::modules());
    }

    public static function permLabel(string $code): string
    {
        return match ($code) {
            'ver' => 'Ver',
            'operar' => 'Operar',
            'si' => 'Sí',
            'corregir' => 'Corregir',
            'admin' => 'Administrar',
            default => 'No',
        };
    }

    /**
     * @return list<UserRole>
     */
    public static function matrixRoles(): array
    {
        return [
            UserRole::Consulta,
            UserRole::Operario,
            UserRole::Logistica,
            UserRole::JefeSector,
            UserRole::Administrador,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function all(string $perm): array
    {
        return [
            'CONSULTA' => $perm,
            'OPERARIO' => $perm,
            'LOGISTICA' => $perm,
            'JEFE_SECTOR' => $perm,
            'ADMINISTRADOR' => $perm,
        ];
    }
}
