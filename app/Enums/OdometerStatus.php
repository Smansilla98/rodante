<?php

namespace App\Enums;

enum OdometerStatus: string
{
    case Pending = 'PENDING';
    case Validated = 'VALIDATED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Validated => 'Registrado',
            self::Rejected => 'Rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Validated => 'green',
            self::Rejected => 'red',
        };
    }
}
