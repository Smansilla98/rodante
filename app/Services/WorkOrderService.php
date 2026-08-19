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

    public function open(User $user, Tire $tire, RetreadShop $shop, WorkOrderType $type, ?string $notes = null): WorkOrder
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para abrir órdenes de trabajo.');
        }
        if ((int) $shop->company_id !== (int) $user->company_id || (int) $tire->company_id !== (int) $user->company_id) {
            throw new DomainException('La recapadora o la cubierta no pertenecen a tu empresa.');
        }
        if ($type === WorkOrderType::Recapado && ! $user->role->canRetireOrRecap()) {
            throw new DomainException('Solo jefe o administrador abren una OT de recapado.');
        }
        if ($tire->status === TireStatus::DeBaja) {
            throw new DomainException('Una cubierta de baja no entra a taller.');
        }
        if ($tire->status === TireStatus::Instalada || $tire->status === TireStatus::Auxilio) {
            throw new DomainException('Retirá la cubierta a stock antes de mandarla al taller.');
        }
        $open = WorkOrder::query()
            ->where('tire_id', $tire->id)
            ->whereIn('status', [WorkOrderStatus::Abierta->value, WorkOrderStatus::EnTaller->value])
            ->exists();
        if ($open) {
            throw new DomainException('Esa cubierta ya tiene una orden abierta.');
        }

        return DB::transaction(function () use ($user, $tire, $shop, $type, $notes) {
            $order = WorkOrder::create([
                'company_id' => $user->company_id,
                'number' => $this->numbers->next((int) $user->company_id, 'work_order', 'OT-'),
                'tire_id' => $tire->id,
                'retread_shop_id' => $shop->id,
                'type' => $type,
                'status' => WorkOrderStatus::Abierta,
                'notes' => $notes,
                'opened_by' => $user->id,
            ]);
            $this->audit->log('work_order.opened', $order);
            $this->alerts->notifyCompany(
                (int) $user->company_id,
                'Orden de trabajo '.$order->number,
                $tire->displayName().' a '.$shop->name.' ('.$type->label().').',
                route('work-orders.show', $order),
                ['ADMINISTRADOR', 'JEFE_SECTOR', 'LOGISTICA'],
            );

            return $order;
        });
    }

    public function sendToShop(WorkOrder $order, User $user): WorkOrder
    {
        if ($order->status !== WorkOrderStatus::Abierta) {
            throw new DomainException('Solo una OT abierta se manda al taller.');
        }

        return DB::transaction(function () use ($order, $user) {
            $tire = $order->tire;
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
            $order->update([
                'status' => WorkOrderStatus::EnTaller,
                'sent_at' => now(),
            ]);
            $this->audit->log('work_order.sent', $order);

            return $order->fresh();
        });
    }

    public function close(WorkOrder $order, User $user, ?float $cost = null, ?string $notes = null): WorkOrder
    {
        if (! $order->status->isOpen()) {
            throw new DomainException('La orden ya está cerrada o cancelada.');
        }

        return DB::transaction(function () use ($order, $user, $cost, $notes) {
            $tire = $order->tire;
            if ($order->type === WorkOrderType::Recapado) {
                $this->incidents->register($tire, [
                    'type' => IncidentType::Recapado->value,
                    'description' => 'Cierre OT '.$order->number,
                    'notes' => $notes,
                ], $user);
            } elseif ($tire->status === TireStatus::EnReparacion) {
                $this->operations->returnToStock($tire, $user, 'Cierre OT '.$order->number);
            }
            $order->update([
                'status' => WorkOrderStatus::Cerrada,
                'cost' => $cost,
                'notes' => $notes ?: $order->notes,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
            if ($cost !== null && $cost > 0) {
                $this->costs->record(
                    $user,
                    $order->type === WorkOrderType::Recapado ? 'RECAP' : 'REPAIR',
                    $cost,
                    $order,
                    $tire,
                    $order->number,
                );
            }
            $this->audit->log('work_order.closed', $order);
            $this->alerts->notifyCompany(
                (int) $order->company_id,
                'OT '.$order->number.' cerrada',
                $tire->displayName().' volvió del taller.',
                route('tires.show', $tire),
                ['ADMINISTRADOR', 'JEFE_SECTOR', 'LOGISTICA', 'OPERARIO'],
            );

            return $order->fresh();
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
        $this->audit->log('work_order.cancelled', $order);

        return $order->fresh();
    }
}
