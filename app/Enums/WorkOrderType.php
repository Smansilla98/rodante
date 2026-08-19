<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case Recapado = 'RECAPADO';
    case Reparacion = 'REPARACION';

    public function label(): string
    {
        return match ($this) {
            self::Recapado => 'Recapado',
            self::Reparacion => 'Reparación (parche)',
        };
    }
}
