<?php

namespace App\Services;

use App\Enums\TireStatus;
use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Models\TireCurrentLocation;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IntegrityService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function findings(?User $user = null): Collection
    {
        $user ??= auth()->user();
        if (! $user) {
            return $this->computeFindings(null);
        }

        $payload = Cache::remember(
            $this->cacheKey($user, 'findings'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeFindings($user)->all(),
        );

        return collect($payload);
    }

    public function count(?User $user = null): int
    {
        $user ??= auth()->user();
        if (! $user) {
            return $this->computeFindings(null)->count();
        }

        return (int) Cache::remember(
            $this->cacheKey($user, 'count'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeFindings($user)->count(),
        );
    }

    public function invalidateCompany(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        $key = $this->versionKey($companyId);
        Cache::forever($key, $this->version($companyId) + 1);
    }

    public function invalidateForTire(Tire $tire): void
    {
        $this->invalidateCompany($tire->company_id ? (int) $tire->company_id : null);
    }

    public function version(int $companyId): int
    {
        return (int) Cache::get($this->versionKey($companyId), 0);
    }

    private function versionKey(int $companyId): string
    {
        return 'rodante:integrity:ver:'.$companyId;
    }

    private function cacheKey(User $user, string $suffix): string
    {
        $companyId = AccessScope::companyId($user) ?? 0;
        $ver = $this->version($companyId);
        $scope = AccessScope::seesEverything($user)
            ? 'all'
            : 's'.md5(json_encode([
                AccessScope::fleetIds($user),
                AccessScope::visibleBaseIds($user),
            ]));

        return "rodante:integrity:{$suffix}:{$companyId}:{$ver}:{$scope}";
    }

    private function computeFindings(?User $user): Collection
    {
        $tires = Tire::query();
        if ($user) {
            AccessScope::tires($tires, $user);
        }
        $ids = (clone $tires)->select('id');

        $items = collect();

        $withoutLocation = (clone $tires)
            ->whereDoesntHave('currentLocation')
            ->where('status', '!=', TireStatus::DeBaja)
            ->limit(50)
            ->get(['id', 'individual_number', 'tire_model_id']);
        foreach ($withoutLocation as $tire) {
            $items->push($this->row('NO_LOCATION', $tire, 'No tiene ubicación actual. Debería haber una sola fila en stock, unidad o taller.'));
        }

        $installedOffUnit = TireCurrentLocation::query()
            ->whereIn('tire_id', $ids)
            ->whereIn('location_kind', ['INSTALADA', 'AUXILIO'])
            ->where(function ($q) {
                $q->whereNull('unit_id')->orWhereNull('position_id');
            })
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($installedOffUnit as $loc) {
            if ($loc->tire) {
                $items->push($this->row('MOUNTED_WITHOUT_SLOT', $loc->tire, 'Figura montada pero no tiene unidad o posición.'));
            }
        }

        $stockOnUnit = TireCurrentLocation::query()
            ->whereIn('tire_id', $ids)
            ->where('location_kind', 'STOCK')
            ->whereNotNull('unit_id')
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($stockOnUnit as $loc) {
            if ($loc->tire) {
                $items->push($this->row('STOCK_ON_UNIT', $loc->tire, 'Está en STOCK pero sigue apuntando a una unidad.'));
            }
        }

        $openCounts = TireAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('tire_id', $ids)
            ->select('tire_id', DB::raw('count(*) as total'))
            ->groupBy('tire_id')
            ->having('total', '>', 1)
            ->limit(50)
            ->get();
        foreach ($openCounts as $row) {
            $tire = Tire::query()->find($row->tire_id);
            if ($tire) {
                $items->push($this->row('MULTI_OPEN_ASSIGNMENT', $tire, 'Tiene '.$row->total.' assignments abiertos. Solo puede haber uno.'));
            }
        }

        $openNullKeys = TireAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('tire_id', $ids)
            ->where(function ($q) {
                $q->whereNull('open_tire_id')->orWhereNull('open_key');
            })
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($openNullKeys as $assignment) {
            if ($assignment->tire) {
                $items->push($this->row(
                    'OPEN_ASSIGNMENT_NULL_KEY',
                    $assignment->tire,
                    'Assignment abierto sin open_tire_id/open_key. Riesgo de duplicados si se bypasea Eloquent.'
                ));
            }
        }

        $openWrongStatus = TireAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('tire_id', $ids)
            ->whereHas('tire', fn ($q) => $q->whereNotIn('status', [TireStatus::Instalada, TireStatus::Auxilio]))
            ->with('tire')
            ->limit(50)
            ->get();
        foreach ($openWrongStatus as $assignment) {
            if ($assignment->tire) {
                $items->push($this->row('OPEN_ASSIGNMENT_WRONG_STATUS', $assignment->tire, 'Tiene assignment abierto pero el estado no es instalada ni auxilio.'));
            }
        }

        $mountedWithoutAssignment = (clone $tires)
            ->whereIn('status', [TireStatus::Instalada, TireStatus::Auxilio])
            ->whereDoesntHave('openAssignment')
            ->limit(50)
            ->get(['id', 'individual_number', 'tire_model_id']);
        foreach ($mountedWithoutAssignment as $tire) {
            $items->push($this->row('MOUNTED_WITHOUT_ASSIGNMENT', $tire, 'Está montada y no hay assignment abierto. El kilometraje puede quedar colgado.'));
        }

        $negativeKm = TireAssignmentSegment::query()
            ->where('km_delta', '<', 0)
            ->whereHas('assignment', fn ($q) => $q->whereIn('tire_id', $ids))
            ->with('assignment.tire')
            ->limit(50)
            ->get();
        foreach ($negativeKm as $segment) {
            $tire = $segment->assignment?->tire;
            if ($tire) {
                $items->push($this->row('NEGATIVE_KM', $tire, 'Hay un tramo con km negativo ('.$segment->km_delta.').'));
            }
        }

        return $items->unique(fn ($row) => $row['code'].'-'.$row['tire_id'])->values();
    }

    private function row(string $code, Tire $tire, string $message): array
    {
        return [
            'code' => $code,
            'tire_id' => $tire->id,
            'label' => $tire->displayName(),
            'message' => $message,
            'url' => route('tires.show', $tire),
        ];
    }
}
