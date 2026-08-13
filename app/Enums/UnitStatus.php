<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Activa = 'ACTIVA';
    case Inactiva = 'INACTIVA';
    case Spare = 'SPARE';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Inactiva => 'Inactiva',
            self::Spare => 'Spare',
        };
    }
}
