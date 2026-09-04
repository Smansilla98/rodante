<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Exceptions\DomainException;
use App\Models\RetreadShop;
use App\Models\Tire;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private CostService $costs,
        private IncidentService $incidents,
        private TireOperationService $operations,
        private LocationService $locations,
        private AlertService $alerts,
        private AuditService $audit,
    ) {}

    public function open(User $user, Tire|iterable $tires, RetreadShop $shop, WorkOrderType $type, ?string $notes = null): WorkOrder
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para abrir órdenes de trabajo.');
        }
        if ((int) $shop->company_id !== (int) $user->company_id) {
            throw new DomainException('La recapadora no pertenece a tu empresa.');
        }
        if ($type === WorkOrderType::Recapado && ! $user->role->canRetireOrRecap()) {
            throw new DomainException('Solo jefe o administrador abren una OT de recapado.');
        }

        $tires = $this->normalizeTires($tires);
        if ($tires->isEmpty()) {
            throw new DomainException('Elegí al menos una cubierta.');
        }
        if ($type === WorkOrderType::Reparacion && $tires->count() > 1) {
            throw new DomainException('La reparación es de una sola cubierta. Para recapado podés mandar varias juntas.');
        }

        foreach ($tires as $tire) {
            $this->assertTireCanEnterShop($user, $tire);
        }

        return DB::transaction(function () use ($user, $tires, $shop, $type, $notes) {
            $primary = $tires->first();
            $order = WorkOrder::create([
                'company_id' => $user->company_id,
                'number' => $this->numbers->next((int) $user->company_id, 'work_order', 'OT-'),
                'tire_id' => $primary->id,
                'retread_shop_id' => $shop->id,
                'type' => $type,
                'status' => WorkOrderStatus::Abierta,
                'notes' => $notes,
                'opened_by' => $user->id,
            ]);

            foreach ($tires as $tire) {
                WorkOrderItem::create([
                    'work_order_id' => $order->id,
                    'tire_id' => $tire->id,
                    'open_tire_id' => $tire->id,
                ]);
            }

            $this->audit->log('work_order.opened', $order, null, [
                'tires' => $tires->count(),
                'type' => $type->value,
            ]);
            $this->alerts->notifyCompany(
                (int) $user->company_id,
                'Orden de trabajo '.$order->number,
                $this->tirePhrase($tires).' a '.$shop->name.' ('.$type->label().').',
                route('work-orders.show', $order),
                ['ADMINISTRADOR', 'JEFE_SECTOR', 'LOGISTICA'],
            );

            return $order->fresh(['items.tire.model', 'items.tire.brand', 'tire.model', 'tire.brand', 'shop']);
        });
    }

    public function sendToShop(WorkOrder $order, User $user): WorkOrder
    {
        if ($order->status !== WorkOrderStatus::Abierta) {
            throw new DomainException('Solo una OT abierta se manda al taller.');
        }

        return DB::transaction(function () use ($order, $user) {
            foreach ($order->tiresOnOrder() as $tire) {
                if ($tire->status === TireStatus::Stock) {
                    $baseId = $tire->currentLocation?->base_id;
                    $this->locations->place($tire, LocationKind::EnReparacion, $baseId);
                    $tire->movements()->create([
                        'type' => MovementType::ToRepair,
                        'occurred_at' => now(),
                        'from_base_id' => $baseId,
                        'to_base_id' => $baseId,
                        'user_id' => $user->id,
                        'notes' => 'Envío a taller '.$order->number,
                        'created_at' => now(),
                    ]);
                }
            }
            $order->update([
                'status' => WorkOrderStatus::EnTaller,
                'sent_at' => now(),
            ]);
            $this->audit->log('work_order.sent', $order);

            return $order->fresh(['items.tire.model', 'items.tire.brand', 'tire.model', 'tire.brand', 'shop']);
        });
    }

    public function close(WorkOrder $order, User $user, ?float $cost = null, ?string $notes = null): WorkOrder
    {
        if (! $order->status->isOpen()) {
            throw new DomainException('La orden ya está cerrada o cancelada.');
        }

        return DB::transaction(function () use ($order, $user, $cost, $notes) {
            $tires = $order->tiresOnOrder();

            foreach ($tires as $tire) {
                if ($order->type === WorkOrderType::Recapado) {
                    $this->incidents->register($tire, [
                        'type' => IncidentType::Recapado->value,
                        'description' => 'Cierre OT '.$order->number,
                        'notes' => $notes,
                    ], $user);
                } elseif ($tire->status === TireStatus::EnReparacion) {
                    $this->operations->returnToStock($tire, $user, 'Cierre OT '.$order->number);
                }
            }

            $order->update([
                'status' => WorkOrderStatus::Cerrada,
                'cost' => $cost,
                'notes' => $notes ?: $order->notes,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            WorkOrderItem::query()
                ->where('work_order_id', $order->id)
                ->update(['open_tire_id' => null]);

            if ($cost !== null && $cost > 0 && $tires->isNotEmpty()) {
                foreach ($this->splitCost($cost, $tires->count()) as $i => $share) {
                    $tire = $tires[$i];
                    $attribution = $this->costs->attributionFromTire($tire);
                    $attribution['unit_price'] = $share;
                    $attribution['quantity'] = 1;
                    $this->costs->record(
                        $user,
                        $order->type === WorkOrderType::Recapado ? 'RECAP' : 'REPAIR',
                        $share,
                        $order,
                        $tire,
                        $order->number,
                        $attribution,
                    );
                }
            }

            $this->audit->log('work_order.closed', $order);
            $primary = $tires->first();
            $this->alerts->notifyCompany(
                (int) $order->company_id,
                'OT '.$order->number.' cerrada',
                $this->tirePhrase($tires).' volvió del taller.',
                $primary ? route('tires.show', $primary) : route('work-orders.show', $order),
                ['ADMINISTRADOR', 'JEFE_SECTOR', 'LOGISTICA', 'OPERARIO'],
            );

            return $order->fresh(['items.tire.model', 'items.tire.brand', 'tire.model', 'tire.brand', 'shop']);
        });
    }

    public function cancel(WorkOrder $order, User $user, ?string $notes = null): WorkOrder
    {
        if ($order->status === WorkOrderStatus::Cerrada) {
            throw new DomainException('Una OT cerrada no se cancela. Quedó en el historial.');
        }
        $order->update([
            'status' => WorkOrderStatus::Cancelada,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'notes' => trim(($order->notes ? $order->notes."\n" : '').($notes ?: 'Cancelada')),
        ]);

        WorkOrderItem::query()
            ->where('work_order_id', $order->id)
            ->update(['open_tire_id' => null]);

        $this->audit->log('work_order.cancelled', $order);

        return $order->fresh(['items.tire.model', 'items.tire.brand', 'tire.model', 'tire.brand', 'shop']);
    }

    /**
     * @param  Tire|iterable<int, Tire>  $tires
     * @return Collection<int, Tire>
     */
    private function normalizeTires(Tire|iterable $tires): Collection
    {
        $list = $tires instanceof Tire ? collect([$tires]) : collect($tires);

        return $list
            ->filter(fn ($tire) => $tire instanceof Tire)
            ->unique('id')
            ->values();
    }

    private function assertTireCanEnterShop(User $user, Tire $tire): void
    {
        if ((int) $tire->company_id !== (int) $user->company_id) {
            throw new DomainException('La cubierta '.$tire->displayName().' no pertenece a tu empresa.');
        }
        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException($tire->displayName().' está de baja y no entra a taller.');
        }
        if ($tire->status === TireStatus::Instalada || $tire->status === TireStatus::Auxilio) {
            throw new DomainException('Retirá '.$tire->displayName().' a stock antes de mandarla al taller.');
        }

        $open = WorkOrderItem::query()
            ->where('tire_id', $tire->id)
            ->whereNotNull('open_tire_id')
            ->exists()
            || WorkOrder::query()
                ->where('tire_id', $tire->id)
                ->whereIn('status', [WorkOrderStatus::Abierta->value, WorkOrderStatus::EnTaller->value])
                ->exists();

        if ($open) {
            throw new DomainException($tire->displayName().' ya tiene una orden abierta.');
        }
    }

    /**
     * @return list<float>
     */
    private function splitCost(float $cost, int $count): array
    {
        $count = max(1, $count);
        $cents = (int) round($cost * 100);
        $base = intdiv($cents, $count);
        $remainder = $cents % $count;
        $shares = [];

        for ($i = 0; $i < $count; $i++) {
            $shares[] = ($base + ($i === $count - 1 ? $remainder : 0)) / 100;
        }

        return $shares;
    }

    private function tirePhrase(Collection $tires): string
    {
        if ($tires->count() === 1) {
            return $tires->first()->displayName();
        }

        return $tires->count().' cubiertas ('.$tires->take(2)->map->displayName()->implode(', ').($tires->count() > 2 ? '…' : '').')';
    }
}
