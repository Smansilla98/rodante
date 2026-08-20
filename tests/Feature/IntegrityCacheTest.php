<?php

namespace Tests\Feature;

use App\Services\IntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class IntegrityCacheTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        Cache::flush();
    }

    public function test_integrity_count_is_served_from_cache_on_second_call(): void
    {
        $this->purchaseTires(1, 81001);
        $integrity = app(IntegrityService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $integrity->count($this->admin);
        $queriesFirst = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $integrity->count($this->admin);
        $queriesSecond = count(DB::getQueryLog());

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(5, $queriesFirst);
        $this->assertLessThan($queriesFirst, $queriesSecond);
        $this->assertLessThanOrEqual(2, $queriesSecond);
    }

    public function test_location_change_invalidates_integrity_cache(): void
    {
        [$tire] = $this->purchaseTires(1, 81002);
        $integrity = app(IntegrityService::class);

        $this->assertSame(0, $integrity->count($this->admin));

        $unit = $this->createTractor();
        $tire->currentLocation->update([
            'unit_id' => $unit->id,
            'location_kind' => 'STOCK',
        ]);

        $this->assertSame(1, $integrity->count($this->admin));
        $this->get(route('integrity.index'))
            ->assertOk()
            ->assertSee('STOCK_ON_UNIT');
    }

    public function test_dashboard_integrity_kpi_uses_cached_count(): void
    {
        [$tire] = $this->purchaseTires(1, 81003);
        $integrity = app(IntegrityService::class);
        $this->assertSame(0, $integrity->count($this->admin));

        $unit = $this->createTractor();
        $tire->currentLocation->update([
            'unit_id' => $unit->id,
            'location_kind' => 'STOCK',
        ]);

        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(1, app(IntegrityService::class)->count($this->admin));
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Integridad[\s\S]*?<div class="queue__v">1<\/div>/', $html);
    }
}
