<?php

namespace App\Enums;

enum InventoryLineDelta: string
{
    case Ok = 'OK';
    case Missing = 'MISSING';
    case Unexpected = 'UNEXPECTED';
    case WrongBase = 'WRONG_BASE';
    case Mounted = 'MOUNTED';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Missing => 'Faltante',
            self::Unexpected => 'Sobrante',
            self::WrongBase => 'Otra base',
            self::Mounted => 'Montada / fuera de depósito',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'green',
            self::Missing => 'red',
            self::Unexpected, self::WrongBase => 'amber',
            self::Mounted => 'blue',
        };
    }
}
