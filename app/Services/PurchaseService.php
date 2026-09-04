<?php

namespace App\Services;

use App\Enums\LocationKind;
use App\Enums\MovementType;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\DocumentCounter;
use App\Models\Tire;
use App\Models\TireLifecycle;
use App\Models\TireModel;
use App\Models\TirePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private LocationService $locations,
        private AuditService $audit,
        private DocumentNumberService $numbers,
    ) {}

    public function create(array $data, User $user): TirePurchase
    {
        return DB::transaction(function () use ($data, $user) {
            $purchase = TirePurchase::create([
                'company_id' => $user->company_id,
                'number' => $this->numbers->next((int) $user->company_id, 'purchase', 'OC-'),
                'supplier_id' => $data['supplier_id'],
                'base_id' => $data['base_id'],
                'user_id' => $user->id,
                'purchased_at' => $data['purchased_at'],
                'status' => TirePurchase::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $this->assertItemMatchesCatalog($item);
                $dot = Tire::normalizeDot($item['dot'] ?? null);
                if ($dot && (int) $item['quantity'] !== 1) {
                    throw new DomainException(
                        'El DOT es por cubierta. Con cantidad mayor a 1, cargalo después en cada ficha.'
                    );
                }
                if ($dot && Tire::where('company_id', $user->company_id)->where('dot', $dot)->exists()) {
                    throw new DomainException("El DOT {$dot} ya está cargado en otra cubierta.");
                }
                $purchase->items()->create([
                    'tire_brand_id' => $item['tire_brand_id'],
                    'tire_model_id' => $item['tire_model_id'],
                    'tire_size_id' => $item['tire_size_id'],
                    'quantity' => $item['quantity'],
                    'first_number' => $item['first_number'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'dot' => $dot,
                ]);
            }

            $this->audit->log('purchase.created', $purchase);

            return $purchase->load('items');
        });
    }

    private function assertItemMatchesCatalog(array $item): void
    {
        $model = TireModel::with('brand', 'sizes')->find($item['tire_model_id'] ?? null);
        if (! $model) {
            throw new DomainException('Elegí un modelo de cubierta.');
        }
        if ((int) $model->tire_brand_id !== (int) $item['tire_brand_id']) {
            throw new DomainException(
                $model->code.' es de '.$model->brand->name.'. No se puede cargar con otra marca.'
            );
        }
        if (! $model->sizes->contains('id', (int) $item['tire_size_id'])) {
            throw new DomainException(
                $model->brand->name.' '.$model->code.' no se fabrica en esa medida.'
            );
        }
    }

    public function confirm(TirePurchase $purchase, User $user): TirePurchase
    {
        if ($purchase->isConfirmed()) {
            throw new DomainException('La compra ya está confirmada y no puede modificarse.');
        }

        return DB::transaction(function () use ($purchase, $user) {
            $purchase->load('items');
            $companyId = (int) ($purchase->company_id ?: $user->company_id);
            $this->numbers->ensureAtLeast(
                $companyId,
                'tire_individual',
                (int) Tire::query()->where('company_id', $companyId)->max('individual_number'),
            );

            foreach ($purchase->items as $item) {
                if ($item->first_number) {
                    $start = (int) $item->first_number;
                } else {
                    $start = $this->allocateIndividualRange($companyId, (int) $item->quantity);
                }
                $end = $start + $item->quantity - 1;
                $this->numbers->ensureAtLeast($companyId, 'tire_individual', $end);

                for ($number = $start; $number <= $end; $number++) {
                    if (Tire::where('company_id', $purchase->company_id)->where('individual_number', $number)->exists()) {
                        throw new DomainException("El número individual {$number} ya existe en la empresa.");
                    }

                    $dot = $item->quantity === 1 ? Tire::normalizeDot($item->dot) : null;
                    if ($dot && Tire::where('company_id', $purchase->company_id)->where('dot', $dot)->exists()) {
                        throw new DomainException("El DOT {$dot} ya está cargado en otra cubierta.");
                    }

                    $tire = Tire::create([
                        'company_id' => $purchase->company_id ?? $user->company_id,
                        'individual_number' => $number,
                        'dot' => $dot,
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
            }

            $purchase->update([
                'status' => TirePurchase::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            $costs = app(CostService::class);
            $purchase->load('items.tires');
            foreach ($purchase->items as $item) {
                $unitCost = $item->unit_cost !== null ? (float) $item->unit_cost : null;
                if ($unitCost === null || $unitCost <= 0) {
                    continue;
                }
                foreach ($item->tires as $tire) {
                    $costs->record(
                        $user,
                        'PURCHASE',
                        $unitCost,
                        $purchase,
                        $tire,
                        $purchase->number,
                        [
                            'unit_price' => $unitCost,
                            'quantity' => 1,
                        ],
                    );
                }
            }

            $this->audit->log('purchase.confirmed', $purchase);

            return $purchase->fresh(['items.tires']);
        });
    }

    public function updateDraft(TirePurchase $purchase, array $data): TirePurchase
    {
        if ($purchase->isConfirmed()) {
            throw new DomainException('Una compra confirmada no se puede editar.');
        }

        $purchase->update([
            'supplier_id' => $data['supplier_id'],
            'base_id' => $data['base_id'],
            'purchased_at' => $data['purchased_at'],
            'notes' => $data['notes'] ?? $purchase->notes,
        ]);
        $this->audit->log('purchase.updated', $purchase->fresh());

        return $purchase->fresh();
    }

    public function discard(TirePurchase $purchase): void
    {
        if ($purchase->isConfirmed()) {
            throw new DomainException('Una compra confirmada no se puede borrar.');
        }

        DB::transaction(function () use ($purchase) {
            $this->audit->log('purchase.discarded', $purchase, null, ['number' => $purchase->number]);
            $purchase->items()->delete();
            $purchase->delete();
        });
    }

    private function allocateIndividualRange(int $companyId, int $quantity): int
    {
        return DB::transaction(function () use ($companyId, $quantity) {
            DocumentCounter::query()->insertOrIgnore([
                'company_id' => $companyId,
                'document' => 'tire_individual',
                'value' => 0,
            ]);

            $row = DocumentCounter::query()
                ->where('company_id', $companyId)
                ->where('document', 'tire_individual')
                ->lockForUpdate()
                ->firstOrFail();

            $maxExisting = (int) Tire::query()->where('company_id', $companyId)->max('individual_number');
            if ((int) $row->value < $maxExisting) {
                $row->value = $maxExisting;
            }

            $start = (int) $row->value + 1;
            $row->value = $start + $quantity - 1;
            $row->save();

            return $start;
        });
    }
}
