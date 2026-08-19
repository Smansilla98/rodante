<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Abierta = 'ABIERTA';
    case EnTaller = 'EN_TALLER';
    case Cerrada = 'CERRADA';
    case Cancelada = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnTaller => 'En taller',
            self::Cerrada => 'Cerrada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Abierta, self::EnTaller], true);
    }
}
