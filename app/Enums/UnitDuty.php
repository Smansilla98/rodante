<?php

namespace App\Enums;

enum UnitDuty: string
{
    case LargaDistancia = 'LARGA_DISTANCIA';
    case Regional = 'REGIONAL';
    case Urbano = 'URBANO';
    case Nieve = 'NIEVE';
    case Mixto = 'MIXTO';

    public function label(): string
    {
        return match ($this) {
            self::LargaDistancia => 'Larga distancia',
            self::Regional => 'Regional',
            self::Urbano => 'Urbano',
            self::Nieve => 'Nieve / cordillera',
            self::Mixto => 'Mixto',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::LargaDistancia => 'Ruta nacional; prioriza cubiertas de rodado continuo.',
            self::Regional => 'Tramos cortos y bases cercanas.',
            self::Urbano => 'Maniobras frecuentes; desgaste de dirección.',
            self::Nieve => 'Requiere cubiertas de invierno / aptas para cadenas.',
            self::Mixto => 'Combina asfalto y ripio según la carga.',
        };
    }

    public function prefersWinterTires(): bool
    {
        return $this === self::Nieve;
    }
}
