<?php

namespace App\Enums;

enum MovementType: string
{
    case PurchaseIn = 'PURCHASE_IN';
    case RemoveToStock = 'REMOVE_TO_STOCK';
    case Install = 'INSTALL';
    case Rotate = 'ROTATE';
    case ToReserva = 'TO_RESERVA';
    case FromReserva = 'FROM_RESERVA';
    case ToSpare = 'TO_SPARE';
    case FromSpare = 'FROM_SPARE';
    case ToRepair = 'TO_REPAIR';
    case FromRepair = 'FROM_REPAIR';
    case Retire = 'RETIRE';
    case TransferBase = 'TRANSFER_BASE';
    case Correction = 'CORRECTION';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Ingreso por compra',
            self::RemoveToStock => 'Retiro a stock',
            self::Install => 'Instalación',
            self::Rotate => 'Rotación',
            self::ToReserva => 'Pase a reserva',
            self::FromReserva => 'Salida de reserva',
            self::ToSpare => 'Pase a auxilio',
            self::FromSpare => 'Salida de auxilio',
            self::ToRepair => 'Pase a reparación',
            self::FromRepair => 'Salida de reparación',
            self::Retire => 'Baja',
            self::TransferBase => 'Cambio de base',
            self::Correction => 'Corrección',
        };
    }
}
