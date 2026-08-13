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
}
