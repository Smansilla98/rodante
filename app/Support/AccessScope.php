<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TirePurchase;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    public static function companyId(User $user): ?int
    {
        return $user->company_id ? (int) $user->company_id : null;
    }

    public static function applyCompany(Builder $query, User $user, string $column = 'company_id'): Builder
    {
        $companyId = self::companyId($user);
        $table = $query->getModel()->getTable();
        if (! $companyId) {
            return $query->whereRaw('1 = 0');
        }
        $query->where($table.'.'.$column, $companyId);

        return $query;
    }

    public static function fleetIds(User $user): array
    {
        if ($user->role === UserRole::Administrador) {
            return [];
        }

        return $user->fleets()->pluck('fleets.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function baseIds(User $user): array
    {
        if ($user->role === UserRole::Administrador) {
            return [];
        }

        return $user->bases()->pluck('bases.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function visibleBaseIds(User $user): array
    {
        if (self::seesEverything($user)) {
            return [];
        }

        $bases = self::baseIds($user);
        $fleets = self::fleetIds($user);
        if ($fleets !== []) {
            $fromUnits = FleetUnit::query()
                ->when(self::companyId($user), fn ($q, $id) => $q->where('company_id', $id))
                ->whereIn('fleet_id', $fleets)
                ->pluck('base_id')
                ->all();
            $bases = array_merge($bases, $fromUnits);
        }

        return array_values(array_unique(array_filter(array_map('intval', $bases))));
    }

    public static function seesEverything(User $user): bool
    {
        return $user->role === UserRole::Administrador;
    }

    public static function units(Builder $query, User $user): Builder
    {
        self::applyCompany($query, $user);
        if (self::seesEverything($user)) {
            return $query;
        }

        $fleets = self::fleetIds($user);
        $bases = self::baseIds($user);
        if ($fleets === [] && $bases === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($fleets, $bases) {
            if ($fleets !== []) {
                $inner->whereIn('fleet_id', $fleets);
            }
            if ($bases !== []) {
                $method = $fleets !== [] ? 'orWhereIn' : 'whereIn';
                $inner->{$method}('base_id', $bases);
            }
        });
    }

    public static function tires(Builder $query, User $user): Builder
    {
        self::applyCompany($query, $user);
        if (self::seesEverything($user)) {
            return $query;
        }

        $fleets = self::fleetIds($user);
        $bases = self::visibleBaseIds($user);
        if ($fleets === [] && $bases === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($fleets, $bases) {
            if ($fleets !== []) {
                $inner->whereHas('currentLocation.unit', fn (Builder $unit) => $unit->whereIn('fleet_id', $fleets));
            }
            if ($bases !== []) {
                $method = $fleets !== [] ? 'orWhereHas' : 'whereHas';
                $inner->{$method}('currentLocation', fn (Builder $loc) => $loc->whereIn('base_id', $bases));
            }
        });
    }

    public static function purchases(Builder $query, User $user): Builder
    {
        self::applyCompany($query, $user);
        if (self::seesEverything($user)) {
            return $query;
        }

        $bases = self::visibleBaseIds($user);
        if ($bases === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('base_id', $bases);
    }

    public static function workOrders(Builder $query, User $user): Builder
    {
        return self::applyCompany($query, $user);
    }

    public static function auditLogs(Builder $query, User $user): Builder
    {
        return self::applyCompany($query, $user);
    }

    public static function abortUnlessAuditLog(User $user, int $logId): void
    {
        $ok = AuditLog::query()->whereKey($logId);
        self::auditLogs($ok, $user);
        if (! $ok->exists()) {
            abort(404);
        }
    }

    public static function canViewTire(User $user, Tire|int $tire): bool
    {
        $id = $tire instanceof Tire ? (int) $tire->id : $tire;
        $ok = Tire::query()->whereKey($id);
        self::tires($ok, $user);

        return $ok->exists();
    }

    public static function canViewUnit(User $user, FleetUnit|int $unit): bool
    {
        $id = $unit instanceof FleetUnit ? (int) $unit->id : $unit;
        $ok = FleetUnit::query()->whereKey($id);
        self::units($ok, $user);

        return $ok->exists();
    }

    public static function canViewWorkOrder(User $user, WorkOrder|int $order): bool
    {
        $id = $order instanceof WorkOrder ? (int) $order->id : $order;
        $ok = WorkOrder::query()->whereKey($id);
        self::workOrders($ok, $user);

        return $ok->exists();
    }

    public static function abortUnlessTire(User $user, int $tireId): void
    {
        if (! self::canViewTire($user, $tireId)) {
            abort(404);
        }
    }

    public static function abortUnlessUnit(User $user, int $unitId): void
    {
        if (! self::canViewUnit($user, $unitId)) {
            abort(404);
        }
    }

    public static function abortUnlessPurchase(User $user, int $purchaseId): void
    {
        $ok = TirePurchase::query()->whereKey($purchaseId);
        self::purchases($ok, $user);
        if (! $ok->exists()) {
            abort(404);
        }
    }

    public static function abortUnlessWorkOrder(User $user, int $orderId): void
    {
        if (! self::canViewWorkOrder($user, $orderId)) {
            abort(404);
        }
    }
}
