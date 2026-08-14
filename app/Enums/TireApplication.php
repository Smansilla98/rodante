<?php

namespace App\Enums;

enum TireApplication: string
{
    case Direccion = 'DIRECCION';
    case Traccion = 'TRACCION';
    case Arrastre = 'ARRASTRE';
    case Mixto = 'MIXTO';

    public function label(): string
    {
        return match ($this) {
            self::Direccion => 'Dirección',
            self::Traccion => 'Tracción',
            self::Arrastre => 'Arrastre',
            self::Mixto => 'Mixta',
        };
    }
}
