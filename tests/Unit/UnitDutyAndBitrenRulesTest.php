<?php

namespace Tests\Unit;

use App\Enums\UnitDuty;
use App\Models\FleetUnit;
use PHPUnit\Framework\TestCase;

class UnitDutyAndBitrenRulesTest extends TestCase
{
    public function test_nieve_prefers_winter_tires(): void
    {
        $this->assertTrue(UnitDuty::Nieve->prefersWinterTires());
        $this->assertFalse(UnitDuty::LargaDistancia->prefersWinterTires());
        $this->assertSame('Nieve / cordillera', UnitDuty::Nieve->label());
    }

    public function test_bitren_trailer_cap_is_at_least_two(): void
    {
        $this->assertGreaterThanOrEqual(2, FleetUnit::MAX_BITREN_TRAILERS);
    }
}
