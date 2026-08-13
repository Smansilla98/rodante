<?php

namespace App\Enums;

enum TireCondition: string
{
    case Nueva = 'NUEVA';
    case NuevaUsada = 'NUEVA_USADA';
    case Usada = 'USADA';
    case Recapada = 'RECAPADA';
    case Reparada = 'REPARADA';

    public function label(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::NuevaUsada => 'Nueva usada',
            self::Usada => 'Usada',
            self::Recapada => 'Recapada',
            self::Reparada => 'Reparada',
        };
    }
}
