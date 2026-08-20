<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\MovementType;
use App\Enums\TireStatus;
use App\Models\CostEntry;
use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\TireIncident;
use App\Models\TireMovement;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function tireHistory(Tire $tire): Tire
    {
        return $tire->load([
            'brand', 'model', 'size.zones', 'currentLocation.unit', 'currentLocation.position', 'currentLocation.base',
            'lifecycles', 'movements.fromUnit', 'movements.toUnit', 'movements.fromPosition',
            'movements.toPosition', 'movements.user', 'incidents.user',
            'measurements.readings.zone', 'measurements.user', 'assignments.segments.odometerUnit',
            'purchaseItem.purchase',
            'costEntries.fleetUnit',
            'costEntries.unitPosition',
        ]);
    }

    public function timeline(Tire $tire): Collection
    {
        $tire = $this->tireHistory($tire);
        $items = collect();

        foreach ($tire->movements as $movement) {
            $items->push($this->movementItem($movement));
        }

        foreach ($tire->incidents as $incident) {
            $note = $incident->description ?: $incident->notes;
            $items->push([
                'at' => $incident->occurred_at,
                'sort' => ($incident->occurred_at?->timestamp ?? 0).'-i'.$incident->id,
                'code' => 'INC:'.$incident->type->value,
                'kind' => 'incident',
                'kind_label' => 'Incidencia',
                'tone' => $incident->type === IncidentType::Recapado ? 'blue' : 'orange',
                'title' => $incident->type->label(),
                'body' => $incident->type === IncidentType::Pinchadura
                    ? trim('Pinchó en servicio.'.($note ? ' '.$note : ''))
                    : $note,
                'user' => $incident->user?->name,
            ]);
        }

        foreach ($tire->measurements as $measurement) {
            $mm = $measurement->readings->map(fn ($r) => ($r->zone?->name ?? 'Zona').': '.$r->millimeters.' mm')->implode(', ');
            $items->push([
                'at' => $measurement->measured_at,
                'sort' => ($measurement->measured_at?->timestamp ?? 0).'-t'.$measurement->id,
                'code' => 'MEAS',
                'kind' => 'measurement',
                'kind_label' => 'Medición',
                'tone' => $measurement->raises_alert ? 'red' : 'green',
                'title' => $measurement->raises_alert ? 'Desgaste irregular' : 'Profundidad',
                'body' => $mm ?: null,
                'user' => $measurement->user?->name,
            ]);
        }

        foreach ($tire->lifecycles as $life) {
            $items->push([
                'at' => $life->started_at,
                'sort' => ($life->started_at?->timestamp ?? 0).'-l'.$life->id,
                'code' => 'LIFE:'.$life->started_by,
                'kind' => 'life',
                'kind_label' => 'Vida',
                'tone' => 'blue',
                'title' => $life->started_by === 'COMPRA'
                    ? 'Arranca la vida 1'
                    : 'Vida '.$life->life_number.($life->started_by === 'RECAPADO' ? ' por recapado' : ''),
                'body' => $life->ended_at
                    ? 'Cerrada el '.$life->ended_at->format('d/m/Y')
                    : 'Vida en curso',
                'user' => null,
            ]);
        }

        return $this->clusterTimeline($items->sortByDesc('sort')->values());
    }

    public function unitHistory(FleetUnit $unit): Collection
    {
        return TireMovement::query()
            ->with(['tire.brand', 'tire.model', 'fromPosition', 'toPosition'])
            ->where(function ($q) use ($unit) {
                $q->where('from_unit_id', $unit->id)->orWhere('to_unit_id', $unit->id);
            })
            ->orderByDesc('occurred_at')
            ->limit(80)
            ->get();
    }

    public function kilometersByTire(?User $user = null): LengthAwarePaginator
    {
        $query = Tire::query()->with(['brand', 'model', 'size']);
        if ($user) {
            AccessScope::tires($query, $user);
        }

        return $query
            ->withCount([
                'lifecycles',
                'incidents as recaps_count' => fn ($q) => $q->where('type', 'RECAPADO'),
                'incidents as repairs_count' => fn ($q) => $q->where('type', 'REPARACION'),
            ])
            ->orderByDesc('accumulated_km')
            ->paginate(40)
            ->withQueryString();
    }

    public function consumptionByModel(?User $user = null): LengthAwarePaginator
    {
        $query = Tire::query();
        if ($user) {
            AccessScope::tires($query, $user);
        }

        return $query
            ->join('tire_brands', 'tire_brands.id', '=', 'tires.tire_brand_id')
            ->join('tire_models', 'tire_models.id', '=', 'tires.tire_model_id')
            ->select(
                'tire_brands.name as brand',
                'tire_models.code as model',
                DB::raw('count(*) as purchased'),
                DB::raw("sum(case when tires.status = 'INSTALADA' then 1 else 0 end) as installed"),
                DB::raw("sum(case when tires.status = 'STOCK' then 1 else 0 end) as stock"),
                DB::raw("sum(case when tires.status = 'DE_BAJA' then 1 else 0 end) as retired"),
                DB::raw('avg(tires.accumulated_km) as avg_km'),
            )
            ->groupBy('tire_brands.name', 'tire_models.code')
            ->orderBy('brand')
            ->paginate(40)
            ->withQueryString();
    }

    public function costPerKm(?User $user = null): LengthAwarePaginator
    {
        $query = Tire::query()->with(['brand', 'model']);
        if ($user) {
            AccessScope::tires($query, $user);
        }

        return $query
            ->withSum('costEntries', 'amount')
            ->orderByDesc('accumulated_km')
            ->paginate(40)
            ->withQueryString()
            ->through(function (Tire $tire) {
                $cost = (float) ($tire->cost_entries_sum_amount ?? 0);
                $tire->cost_total = $cost;
                $km = (int) $tire->accumulated_km;
                $tire->cost_per_km = ($cost > 0 && $km > 0) ? round($cost / $km, 4) : null;

                return $tire;
            });
    }

    /**
     * Costo asentado por posición (suma de amount con unit_position_id).
     * No altera costPerKm: solo entradas con atribución de posición.
     */
    public function costByPosition(?User $user = null): Collection
    {
        $query = CostEntry::query()
            ->select([
                'unit_position_id',
                DB::raw('sum(amount) as total_amount'),
                DB::raw('count(*) as entries_count'),
                DB::raw('count(distinct tire_id) as tire_count'),
            ])
            ->whereNotNull('unit_position_id')
            ->groupBy('unit_position_id')
            ->orderByDesc('total_amount');

        if ($user) {
            AccessScope::applyCompany($query, $user);
        }

        $rows = $query->get();
        $positions = \App\Models\UnitPosition::query()
            ->whereIn('id', $rows->pluck('unit_position_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function (CostEntry $row) use ($positions) {
            $pos = $positions->get($row->unit_position_id);

            return (object) [
                'unit_position_id' => (int) $row->unit_position_id,
                'position_name' => $pos?->name ?? ('#'.$row->unit_position_id),
                'position_code' => $pos?->code,
                'total_amount' => (float) $row->total_amount,
                'entries_count' => (int) $row->entries_count,
                'tire_count' => (int) $row->tire_count,
            ];
        });
    }

    /**
     * Costo asentado por unidad (suma de amount con fleet_unit_id).
     */
    public function costByUnit(?User $user = null): Collection
    {
        $query = CostEntry::query()
            ->select([
                'fleet_unit_id',
                DB::raw('sum(amount) as total_amount'),
                DB::raw('count(*) as entries_count'),
                DB::raw('count(distinct tire_id) as tire_count'),
            ])
            ->whereNotNull('fleet_unit_id')
            ->groupBy('fleet_unit_id')
            ->orderByDesc('total_amount');

        if ($user) {
            AccessScope::applyCompany($query, $user);
        }

        $rows = $query->get();
        $units = FleetUnit::query()
            ->whereIn('id', $rows->pluck('fleet_unit_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function (CostEntry $row) use ($units) {
            $unit = $units->get($row->fleet_unit_id);

            return (object) [
                'fleet_unit_id' => (int) $row->fleet_unit_id,
                'plate' => $unit?->plate ?? ('#'.$row->fleet_unit_id),
                'total_amount' => (float) $row->total_amount,
                'entries_count' => (int) $row->entries_count,
                'tire_count' => (int) $row->tire_count,
            ];
        });
    }

    public function inventory(?User $user = null): LengthAwarePaginator
    {
        $query = Tire::query()->with(['brand', 'model', 'size', 'currentLocation.base', 'currentLocation.unit']);
        if ($user) {
            AccessScope::tires($query, $user);
        }

        return $query
            ->where('status', '!=', TireStatus::DeBaja)
            ->orderBy('status')
            ->orderBy('individual_number')
            ->paginate(80)
            ->withQueryString();
    }

    public function incidents(?User $user = null): Collection
    {
        $query = TireIncident::query();
        if ($user && ! AccessScope::seesEverything($user)) {
            $tires = Tire::query();
            AccessScope::tires($tires, $user);
            $query->whereIn('tire_id', $tires->select('id'));
        }

        return $query
            ->select('type', DB::raw('count(*) as total'), DB::raw('count(distinct tire_id) as tires'))
            ->groupBy('type')
            ->get();
    }

    private function movementItem(TireMovement $movement): array
    {
        $from = collect([$movement->fromUnit?->plate, $movement->fromPosition?->name])->filter()->implode(' · ');
        $to = collect([$movement->toUnit?->plate, $movement->toPosition?->name])->filter()->implode(' · ');
        $km = $movement->km_delta ? '+'.number_format($movement->km_delta).' km de rodaje' : null;

        [$title, $body, $kindLabel, $tone] = match ($movement->type) {
            MovementType::PurchaseIn => ['Entró a stock por compra', $movement->notes, 'Alta', 'green'],
            MovementType::Install => ['Se montó en la unidad', $to ?: $movement->notes, 'Montaje', 'green'],
            MovementType::RemoveToStock => ['Se bajó de la unidad', collect([$from, $km])->filter()->implode(' · '), 'Ubicación', 'amber'],
            MovementType::Rotate => ['Rotó de posición', trim($from.' → '.$to), 'Montaje', 'amber'],
            MovementType::ToReserva => ['Quedó en reserva', 'No está en una unidad. Para usarla hay que devolverla a stock.', 'Ubicación', 'amber'],
            MovementType::FromReserva => ['Salió de reserva a stock', 'Ya se puede instalar.', 'Ubicación', 'green'],
            MovementType::ToSpare => ['Pasó a auxilio', $to.' · el auxilio no suma km.', 'Montaje', 'slate'],
            MovementType::FromSpare => ['Salió de auxilio', $to, 'Montaje', 'green'],
            MovementType::ToRepair => ['Quedó en reparación', 'Parche: no abre vida nueva. Vuelve por stock.', 'Ubicación', 'orange'],
            MovementType::FromRepair => ['Volvió a stock después del parche', 'Misma vida. Lista para instalar.', 'Ubicación', 'green'],
            MovementType::Retire => ['Baja definitiva', $movement->notes ?: 'Ya no se reinstala. El historial se conserva.', 'Baja', 'red'],
            MovementType::TransferBase => ['Cambió de base', $movement->notes, 'Ubicación', 'slate'],
            MovementType::Correction => ['Corrección de historial', $movement->notes, 'Auditoría', 'slate'],
        };

        return [
            'at' => $movement->occurred_at,
            'sort' => ($movement->occurred_at?->timestamp ?? 0).'-m'.$movement->id,
            'code' => $movement->type->value,
            'kind' => 'movement',
            'kind_label' => $kindLabel,
            'tone' => $tone,
            'title' => $title,
            'body' => $body ?: null,
            'user' => $movement->user?->name,
        ];
    }

    private function clusterTimeline(Collection $items): Collection
    {
        $used = [];
        $clusters = collect();

        foreach ($items as $index => $item) {
            if (isset($used[$index])) {
                continue;
            }
            $codes = $this->clusterCodesFor($item['code']);
            $group = collect([$item]);
            $used[$index] = true;
            if ($codes !== []) {
                foreach ($items as $otherIndex => $other) {
                    if (isset($used[$otherIndex]) || $otherIndex === $index) {
                        continue;
                    }
                    if (! in_array($other['code'], $codes, true)) {
                        continue;
                    }
                    if (! $this->sameMoment($item['at'], $other['at'])) {
                        continue;
                    }
                    $group->push($other);
                    $used[$otherIndex] = true;
                }
            }
            $clusters->push($this->presentCluster($group->sortByDesc('sort')->values()));
        }

        return $clusters->values();
    }

    private function clusterCodesFor(string $code): array
    {
        return match ($code) {
            'INC:PINCHADURA' => ['REMOVE_TO_STOCK', 'TO_REPAIR', 'INC:PINCHADURA'],
            'INC:CAMBIO' => ['REMOVE_TO_STOCK', 'INSTALL', 'INC:CAMBIO'],
            'INC:RECAPADO' => ['LIFE:RECAPADO', 'INC:RECAPADO'],
            'PURCHASE_IN' => ['LIFE:COMPRA', 'PURCHASE_IN'],
            'LIFE:COMPRA' => ['PURCHASE_IN', 'LIFE:COMPRA'],
            'TO_REPAIR' => ['REMOVE_TO_STOCK', 'INC:PINCHADURA', 'TO_REPAIR'],
            default => [],
        };
    }

    private function sameMoment($a, $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return abs($a->diffInSeconds($b)) <= 90;
    }

    private function presentCluster(Collection $group): array
    {
        $codes = $group->pluck('code');
        $first = $group->first();
        $lead = $group->first(fn ($row) => str_starts_with($row['code'], 'INC:'))
            ?? $group->first(fn ($row) => $row['code'] === 'PURCHASE_IN')
            ?? $first;

        $headline = $lead['title'];
        $summary = $lead['body'];
        $tone = $lead['tone'];
        $kindLabel = $lead['kind_label'];

        if ($codes->contains('INC:PINCHADURA')) {
            $remove = $group->firstWhere('code', 'REMOVE_TO_STOCK');
            $headline = 'Pinchadura';
            $summary = collect([
                $remove['body'] ?? null,
                'Quedó en reparación con parche. No abre una vida nueva.',
            ])->filter()->implode(' ');
            $tone = 'orange';
            $kindLabel = 'Incidencia';
        } elseif ($codes->contains('INC:CAMBIO')) {
            $headline = 'Cambio de cubierta';
            $summary = 'Se bajó esta y se montó otra en el mismo lugar.';
            $tone = 'amber';
            $kindLabel = 'Operación';
        } elseif ($codes->contains('PURCHASE_IN')) {
            $headline = 'Alta por compra';
            $summary = $group->firstWhere('code', 'PURCHASE_IN')['body'] ?? 'Ingresó a stock.';
            $tone = 'green';
            $kindLabel = 'Alta';
        } elseif ($codes->contains('INC:RECAPADO')) {
            $headline = 'Recapado';
            $summary = 'Se cerró la vida anterior y arrancó una vida nueva.';
            $tone = 'blue';
            $kindLabel = 'Vida nueva';
        }

        return [
            'at' => $lead['at'] ?? $first['at'],
            'user' => $group->pluck('user')->filter()->first(),
            'headline' => $headline,
            'summary' => $summary,
            'kind_label' => $kindLabel,
            'tone' => $tone,
            'steps' => $group->count() > 1 ? $group->values() : collect(),
            'single' => $group->count() === 1 ? $group->first() : null,
        ];
    }
}
