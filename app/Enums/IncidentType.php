<?php

namespace App\Enums;

enum IncidentType: string
{
    case Pinchadura = 'PINCHADURA';
    case Sopladura = 'SOPLADURA';
    case Parche = 'PARCHE';
    case Reparacion = 'REPARACION';
    case Recapado = 'RECAPADO';
    case Inspeccion = 'INSPECCION';
    case DesgasteIrregular = 'DESGASTE_IRREGULAR';
    case Otra = 'OTRA';

    public function label(): string
    {
        return match ($this) {
            self::Pinchadura => 'Pinchadura',
            self::Sopladura => 'Sopladura',
            self::Parche => 'Parche',
            self::Reparacion => 'Reparación',
            self::Recapado => 'Recapado',
            self::Inspeccion => 'Inspección',
            self::DesgasteIrregular => 'Desgaste irregular',
            self::Otra => 'Otra',
        };
    }

    public function opensNewLife(): bool
    {
        return $this === self::Recapado;
    }
}
