<?php

namespace Tests\Feature;

use App\Models\DocumentCounter;
use App\Models\OdometerReading;
use App\Models\TireAssignmentSegment;
use App\Services\OdometerService;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class EnterpriseHardeningTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_odometer_correction_realigns_segment_km(): void
    {
        $unit = $this->createTractor(100000);
        [$tire] = $this->purchaseTires(1, 93001);
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        $ops = app(TireOperationService::class);

        $ops->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);
        $ops->execute($unit, [
            'odometer' => 100500,
            'removals' => [['tire_id' => $tire->id]],
        ], $this->admin);

        $this->assertSame(500, (int) $tire->fresh()->accumulated_km);

        $reading = OdometerReading::query()->where('value', 100500)->firstOrFail();
        app(OdometerService::class)->update($reading, 100400, $this->admin, 'ajuste');

        $segment = TireAssignmentSegment::query()
            ->whereHas('assignment', fn ($q) => $q->where('tire_id', $tire->id))
            ->whereNotNull('ended_at')
            ->firstOrFail();

        $this->assertSame(100400, (int) $segment->end_odometer);
        $this->assertSame(400, (int) $segment->km_delta);
        $this->assertSame(400, (int) $tire->fresh()->accumulated_km);
    }

    public function test_individual_number_counter_advances_under_confirm(): void
    {
        [$a, $b] = $this->purchaseTires(2, 94000);
        $this->assertSame(94000, (int) $a->individual_number);
        $this->assertSame(94001, (int) $b->individual_number);

        $counter = DocumentCounter::query()
            ->where('company_id', $this->admin->company_id)
            ->where('document', 'tire_individual')
            ->value('value');
        $this->assertSame(94001, (int) $counter);

        $model = \App\Models\TireModel::first();
        $size = $model->sizes()->orderBy('code')->first();
        $withoutFirst = app(\App\Services\PurchaseService::class)->create([
            'supplier_id' => \App\Models\Supplier::first()->id,
            'base_id' => \App\Models\Base::first()->id,
            'purchased_at' => now()->toDateString(),
            'items' => [[
                'tire_brand_id' => $model->tire_brand_id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => 2,
            ]],
        ], $this->admin);
        app(\App\Services\PurchaseService::class)->confirm($withoutFirst, $this->admin);

        $nums = $withoutFirst->fresh()->items->first()->tires()->orderBy('individual_number')->pluck('individual_number')->all();
        $this->assertSame([94002, 94003], array_map('intval', $nums));
    }

    public function test_integrity_flags_open_assignment_without_keys(): void
    {
        [$tire] = $this->purchaseTires(1, 95001);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->first();
        app(TireOperationService::class)->execute($unit, [
            'odometer' => 100000,
            'installations' => [['tire_id' => $tire->id, 'position_id' => $position->id]],
        ], $this->admin);

        \App\Models\TireAssignment::query()
            ->where('tire_id', $tire->id)
            ->whereNull('ended_at')
            ->update(['open_tire_id' => null, 'open_key' => null]);

        app(\App\Services\IntegrityService::class)->invalidateCompany((int) $this->admin->company_id);
        $codes = app(\App\Services\IntegrityService::class)->findings($this->admin)->pluck('code');
        $this->assertTrue($codes->contains('OPEN_ASSIGNMENT_NULL_KEY'));
    }
}
