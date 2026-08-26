<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class ApiOpenApiSurfaceTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    public function test_new_api_endpoints_require_a_token(): void
    {
        foreach ([
            ['GET', '/api/v1/me'],
            ['GET', '/api/v1/bases'],
            ['GET', '/api/v1/work-orders'],
            ['POST', '/api/v1/work-orders'],
            ['GET', '/api/v1/inventory-sessions'],
            ['POST', '/api/v1/tires/lookup?q=1'],
            ['GET', '/api/v1/telemetry'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }
    }

    public function test_lookup_finds_a_scoped_tire_by_number_and_token(): void
    {
        $this->seedDomain();
        [$tire] = $this->purchaseTires(1, 77101);
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/tires/lookup?q=77101')
            ->assertOk()
            ->assertJsonPath('id', $tire->id);
        $this->postJson('/api/v1/tires/lookup?q='.$tire->public_token)
            ->assertOk()
            ->assertJsonPath('individual_number', 77101);
    }

    public function test_openapi_document_exists_and_documents_tires(): void
    {
        $path = public_path('openapi.yaml');
        $this->assertFileExists($path);
        $this->assertStringContainsString('/tires:', file_get_contents($path));
        $this->assertStringContainsString('/tires/{tire}/prediction:', file_get_contents($path));
        $this->assertStringContainsString('/telemetry:', file_get_contents($path));
    }
}
