<?php

namespace App\Enums;

enum InventorySessionStatus: string
{
    case Open = 'OPEN';
    case Counting = 'COUNTING';
    case Review = 'REVIEW';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Counting => 'En conteo',
            self::Review => 'En revisión',
            self::Closed => 'Cerrada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Counting, self::Review], true);
    }

    public function canScan(): bool
    {
        return $this === self::Counting;
    }
}
