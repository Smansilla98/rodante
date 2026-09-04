<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_manifest_and_service_worker_are_served(): void
    {
        $this->get('/manifest.webmanifest')->assertOk();
        $this->get('/sw.js')
            ->assertOk()
            ->assertSee('rodante-shell', false);
    }
}
