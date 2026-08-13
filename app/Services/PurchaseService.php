<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TireLifecycle;
use App\Models\TirePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $user): TirePurchase
    {
        return DB::transaction(function () use ($data, $user) {
            $purchase = TirePurchase::create([
                'number' => $this->nextNumber(),
                'supplier_id' => $data['supplier_id'],
                'base_id' => $data['base_id'],
                'user_id' => $user->id,
                'purchased_at' => $data['purchased_at'],
                'status' => TirePurchase::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $purchase->items()->create([
                    'tire_brand_id' => $item['tire_brand_id'],
                    'tire_model_id' => $item['tire_model_id'],
                    'tire_size_id' => $item['tire_size_id'],
                    'quantity' => $item['quantity'],
                    'first_number' => $item['first_number'] ?? null,
                ]);
            }

            $this->audit->log('purchase.created', $purchase);

            return $purchase->load('items');
        });
    }

    public function confirm(TirePurchase $purchase, User $user): TirePurchase
    {
        if ($purchase->isConfirmed()) {
            throw new DomainException('La compra ya está confirmada y no puede modificarse.');
        }

        return DB::transaction(function () use ($purchase, $user) {
            $purchase->load('items');
            $next = $this->nextIndividualNumber();

            foreach ($purchase->items as $item) {
                $start = $item->first_number ?: $next;
                $end = $start + $item->quantity - 1;

                for ($number = $start; $number <= $end; $number++) {
                    if (Tire::where('individual_number', $number)->exists()) {
                        throw new DomainException("El número individual {$number} ya existe.");
                    }

                    $tire = Tire::create([
                        'individual_number' => $number,
                        'tire_brand_id' => $item->tire_brand_id,
                        'tire_model_id' => $item->tire_model_id,
                        'tire_size_id' => $item->tire_size_id,
                        'tire_purchase_item_id' => $item->id,
                        'status' => TireStatus::Stock,
                        'condition' => TireCondition::Nueva,
                        'accumulated_km' => 0,
                        'purchased_at' => $purchase->purchased_at,
                    ]);

                    $life = TireLifecycle::create([
                        'tire_id' => $tire->id,
                        'life_number' => 1,
                        'started_by' => 'COMPRA',
                        'started_at' => now(),
                        'condition_at_start' => TireCondition::Nueva->value,
                    ]);
                    $tire->update(['current_lifecycle_id' => $life->id]);

                    $this->locations->place($tire, LocationKind::Stock, $purchase->base_id);

                    $tire->movements()->create([
                        'type' => MovementType::PurchaseIn,
                        'occurred_at' => $purchase->purchased_at->startOfDay(),
                        'to_base_id' => $purchase->base_id,
                        'user_id' => $user->id,
                        'notes' => 'Ingreso por compra '.$purchase->number,
                        'created_at' => now(),
                    ]);
                }

                $item->update([
                    'first_number' => $start,
                    'last_number' => $end,
                ]);
                $next = $end + 1;
            }

            $purchase->update([
                'status' => TirePurchase::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            $this->audit->log('purchase.confirmed', $purchase);

            return $purchase->fresh(['items.tires']);
        });
    }

    private function nextNumber(): string
    {
        $count = TirePurchase::count() + 1;

        return 'OC-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    private function nextIndividualNumber(): int
    {
        return ((int) Tire::max('individual_number')) + 1 ?: 1;
    }
}
