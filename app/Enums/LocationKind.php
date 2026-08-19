<?php

namespace App\Enums;

enum LocationKind: string
{
    case Stock = 'STOCK';
    case Reserva = 'RESERVA';
    case Instalada = 'INSTALADA';
    case Auxilio = 'AUXILIO';
    case EnReparacion = 'EN_REPARACION';
    case DeBaja = 'DE_BAJA';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Stock',
            self::Reserva => 'Reserva',
            self::Instalada => 'Instalada',
            self::Auxilio => 'Auxilio',
            self::EnReparacion => 'En reparación',
            self::DeBaja => 'De baja',
        };
    }
}
