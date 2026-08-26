<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MovementReason;
use App\Models\TelemetryEvent;
use App\Models\TirePhoto;
use App\Models\User;
use App\Services\MeasurementService;
use App\Services\PredictiveWearService;
use App\Services\RetirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class LifeTelemetryPredictiveTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_retire_with_photos_stores_evidence_and_shows_on_ficha(): void
    {
        Storage::fake('local');
        [$tire] = $this->purchaseTires(1, 88001);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();
        $photo = UploadedFile::fake()->create('carcasa.jpg', 80, 'image/jpeg');

        $this->get(route('tires.show', $tire))->assertOk();
        $this->post(route('tires.retire', $tire), [
            '_token' => csrf_token(),
            'reason_id' => $reason->id,
            'notes' => 'Fin de vida, carcasa partida',
            'photos' => [$photo],
        ])->assertRedirect();

        $this->assertSame('DE_BAJA', $tire->fresh()->status->value);
        $stored = TirePhoto::where('tire_id', $tire->id)->first();
        $this->assertNotNull($stored);
        $this->assertSame('RETIRE', $stored->kind);
        Storage::disk('local')->assertExists($stored->path);

        $this->get(route('tires.show', $tire))
            ->assertOk()
            ->assertSee('Fotos de baja')
            ->assertSee('Informe de vida');

        $this->get(route('tires.photos.show', [$tire, $stored]))->assertOk();
    }

    public function test_life_report_includes_history_prediction_and_photos(): void
    {
        Storage::fake('local');
        [$tire] = $this->purchaseTires(1, 88002);
        $this->measure($tire, 12, 110000);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();
        app(RetirementService::class)->retire($tire, [
            'reason_id' => $reason->id,
            'notes' => 'Cierre de vida',
            'photos' => [UploadedFile::fake()->create('baja.jpg', 40, 'image/jpeg')],
        ], $this->admin);

        $this->get(route('tires.life-report', $tire))
            ->assertOk()
            ->assertSee('Informe de vida de la cubierta')
            ->assertSee('Pronóstico de desgaste')
            ->assertSee('Historial completo')
            ->assertSee('Fotos de baja')
            ->assertSee('Baja definitiva');

        $this->assertDatabaseHas('telemetry_events', [
            'name' => 'tire.life_report',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_predictive_uses_measurement_odometer_without_ai_key(): void
    {
        config(['services.ai.key' => '']);
        [$tire] = $this->purchaseTires(1, 88003);
        $this->measure($tire, 16, 100000);
        $this->measure($tire, 10, 112000);

        $forecast = app(PredictiveWearService::class)->forecast($tire->fresh());

        $this->assertSame('measurements', $forecast['source']);
        $this->assertFalse($forecast['ai_enabled']);
        $this->assertSame(12000, $forecast['remaining_km']);
        $this->assertEqualsWithDelta(0.5, $forecast['wear_mm_per_1000km'], 0.001);
        $this->assertStringContainsString('12.000', $forecast['narrative']);
        $this->assertStringContainsString('mediciones', mb_strtolower($forecast['narrative']));

        $this->get(route('reports.predictive'))
            ->assertOk()
            ->assertSee('Predictivo de desgaste')
            ->assertSee($tire->fresh()->displayName());
    }

    public function test_ai_narrative_falls_back_when_provider_fails(): void
    {
        config([
            'services.ai.key' => 'test-key',
            'services.ai.url' => 'https://example.test/v1/chat/completions',
        ]);
        Http::fake(['https://example.test/*' => Http::response(['error' => 'down'], 500)]);

        [$tire] = $this->purchaseTires(1, 88004);
        $forecast = app(PredictiveWearService::class)->forecast($tire);

        $this->assertStringContainsString('mediciones', mb_strtolower($forecast['narrative']));
        $this->assertTrue($forecast['ai_enabled']);
    }

    public function test_telemetry_records_login_and_is_hidden_from_consulta(): void
    {
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->get(route('login'));
        $this->post(route('login'), [
            'username' => $this->admin->username,
            'password' => 'password',
            '_token' => csrf_token(),
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('telemetry_events', [
            'name' => 'auth.login',
            'user_id' => $this->admin->id,
        ]);

        $this->get(route('reports.telemetry'))
            ->assertOk()
            ->assertSee('Telemetría de operación')
            ->assertSee('Ingreso');

        $consulta = User::factory()->create(['role' => UserRole::Consulta]);
        $this->actingAs($consulta)
            ->get(route('reports.telemetry'))
            ->assertForbidden();
    }

    public function test_field_identify_records_telemetry_and_shows_life_report_link(): void
    {
        [$tire] = $this->purchaseTires(1, 88005);
        $this->get(route('field.show', $tire))
            ->assertOk()
            ->assertSee('Informe de vida')
            ->assertSee('Pronóstico');

        $this->assertTrue(
            TelemetryEvent::query()->where('name', 'field.identify')->where('user_id', $this->admin->id)->exists()
        );
    }

    public function test_api_prediction_and_life_report_require_auth(): void
    {
        [$tire] = $this->purchaseTires(1, 88006);
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/tires/'.$tire->id.'/prediction')->assertUnauthorized();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/tires/'.$tire->id.'/prediction')
            ->assertOk()
            ->assertJsonPath('threshold_mm', 4);
        $this->getJson('/api/v1/tires/'.$tire->id.'/life-report')
            ->assertOk()
            ->assertJsonPath('tire.id', $tire->id);
    }

    public function test_retire_rejects_more_than_six_photos(): void
    {
        Storage::fake('local');
        [$tire] = $this->purchaseTires(1, 88007);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();
        $photos = [];
        for ($i = 0; $i < 7; $i++) {
            $photos[] = UploadedFile::fake()->create('p'.$i.'.jpg', 20, 'image/jpeg');
        }

        $this->get(route('tires.show', $tire))->assertOk();
        $this->from(route('tires.show', $tire))
            ->post(route('tires.retire', $tire), [
                '_token' => csrf_token(),
                'reason_id' => $reason->id,
                'photos' => $photos,
            ])
            ->assertSessionHasErrors('photos');

        $this->assertSame('STOCK', $tire->fresh()->status->value);
    }

    private function measure($tire, float $mm, int $odometer): void
    {
        $tire->load('size.zones');
        $readings = $tire->size->zones->map(fn ($zone) => [
            'zone_id' => $zone->id,
            'millimeters' => $mm,
        ])->all();

        app(MeasurementService::class)->record($tire, [
            'readings' => $readings,
            'odometer' => $odometer,
        ], $this->admin);
    }
}
