<?php

namespace Tests\Unit;

use App\Models\Tire;
use Tests\TestCase;

class TireDotTest extends TestCase
{
    public function test_normalize_dot_strips_noise_and_uppercases(): void
    {
        $this->assertNull(Tire::normalizeDot(null));
        $this->assertNull(Tire::normalizeDot('   '));
        $this->assertSame('1B3C4D0524', Tire::normalizeDot('1b3c 4d-0524'));
    }

    public function test_manufacture_week_year_from_dot(): void
    {
        $tire = new Tire(['dot' => '1B3C4D0524']);
        $this->assertSame(['week' => 5, 'year' => 2024], $tire->manufactureWeekYear());
        $this->assertSame('Semana 5 / 2024', $tire->manufactureLabel());

        $invalid = new Tire(['dot' => 'ABC']);
        $this->assertNull($invalid->manufactureWeekYear());
    }
}
