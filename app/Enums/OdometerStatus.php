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
            self::Pending => 'Pendiente',
            self::Validated => 'Validado',
            self::Rejected => 'Rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Validated => 'green',
            self::Rejected => 'red',
        };
    }
}
