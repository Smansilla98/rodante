<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class TireMovementTriggerTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los triggers de inmutabilidad se validan únicamente con MySQL.');
        }
        $this->seedDomain();
    }

    public function test_mysql_trigger_rejects_direct_movement_update(): void
    {
        [$tire] = $this->purchaseTires(1, 99001);
        $movementId = $tire->movements()->value('id');

        $this->expectException(QueryException::class);
        DB::table('tire_movements')->where('id', $movementId)->update(['notes' => 'alterado']);
    }
}
