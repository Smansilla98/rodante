<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrador = 'ADMINISTRADOR';
    case JefeSector = 'JEFE_SECTOR';
    case Logistica = 'LOGISTICA';
    case Operario = 'OPERARIO';
    case Consulta = 'CONSULTA';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::JefeSector => 'Jefe de sector',
            self::Logistica => 'Logística',
            self::Operario => 'Operario',
            self::Consulta => 'Consulta',
        };
    }

    public function canWrite(): bool
    {
        return $this !== self::Consulta;
    }

    public function canValidateOdometer(): bool
    {
        return in_array($this, [self::Administrador, self::JefeSector, self::Logistica], true);
    }

    public function canManageCatalogs(): bool
    {
        return $this === self::Administrador;
    }

    public function canManageAbm(): bool
    {
        return $this === self::Administrador;
    }

    public function canRetireOrRecap(): bool
    {
        return in_array($this, [self::Administrador, self::JefeSector], true);
    }

    public function canViewTelemetry(): bool
    {
        return $this->canRetireOrRecap();
    }

    public function canChangeConfiguration(): bool
    {
        return in_array($this, [self::Administrador, self::JefeSector], true);
    }

    public function canManageCouplings(): bool
    {
        return in_array($this, [self::Administrador, self::JefeSector, self::Logistica], true);
    }

    public function dashboardKpis(): array
    {
        $all = ['total', 'stock', 'installed', 'reserve', 'spare', 'repair', 'retired', 'km'];

        return match ($this) {
            self::Consulta => ['total', 'installed', 'km'],
            self::Operario => ['stock', 'installed', 'spare', 'repair'],
            self::Logistica => ['stock', 'installed', 'reserve', 'repair'],
            default => $all,
        };
    }
}
