<?php

namespace Tests\Feature;

use App\Enums\TireStatus;
use App\Models\MovementReason;
use App\Notifications\WeeklyReportNotification;
use App\Services\RetirementService;
use App\Services\TireOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('reports.weekly'))->assertRedirect(route('login'));
    }

    public function test_weekly_page_shows_stock_and_movements_sections(): void
    {
        [$stockTire, $mountedTire] = $this->purchaseTires(2, 76001);
        $unit = $this->createTractor();
        $position = $unit->configuration->positions()->where('is_spare', false)->firstOrFail();

        app(TireOperationService::class)->execute($unit, [
            'odometer' => 120000,
            'installations' => [['tire_id' => $mountedTire->id, 'position_id' => $position->id]],
        ], $this->admin);

        $this->get(route('reports.weekly'))
            ->assertOk()
            ->assertSee('Informe semanal')
            ->assertSee('Stock al momento')
            ->assertSee('Movimientos de la semana')
            ->assertSee('Enviar por correo')
            ->assertSee((string) $stockTire->individual_number)
            ->assertSee($unit->plate);
    }

    public function test_weekly_includes_purchases_and_retirements_when_present(): void
    {
        [$tire] = $this->purchaseTires(1, 76010);
        $reason = MovementReason::where('applies_to', 'BAJA')->firstOrFail();
        app(RetirementService::class)->retire($tire, [
            'reason_id' => $reason->id,
            'notes' => 'Fin de vida informe',
        ], $this->admin);

        $this->get(route('reports.weekly', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Compras')
            ->assertSee('Bajas')
            ->assertSee('Fin de vida informe')
            ->assertSee((string) $tire->individual_number);
    }

    public function test_send_weekly_email_notification(): void
    {
        Notification::fake();
        $this->purchaseTires(1, 76020);

        $this->withSession(['_token' => 'test-csrf-token'])
            ->post(route('reports.weekly.send'), [
                '_token' => 'test-csrf-token',
                'email' => 'gerencia@ejemplo.test',
                'from' => now()->startOfWeek()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertRedirect(route('reports.weekly', [
                'from' => now()->startOfWeek()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertSessionHas('success');

        Notification::assertSentOnDemand(WeeklyReportNotification::class, function (WeeklyReportNotification $notification, array $channels, object $notifiable) {
            return in_array('mail', $channels, true)
                && ($notifiable->routes['mail'] ?? null) === 'gerencia@ejemplo.test'
                && isset($notification->report['stock_counts'])
                && isset($notification->report['movements']);
        });
    }

    public function test_send_requires_valid_email(): void
    {
        $this->withSession(['_token' => 'test-csrf-token'])
            ->from(route('reports.weekly'))
            ->post(route('reports.weekly.send'), [
                '_token' => 'test-csrf-token',
                'email' => 'no-es-mail',
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertRedirect(route('reports.weekly'))
            ->assertSessionHasErrors('email');
    }

    public function test_stock_snapshot_counts_current_stock(): void
    {
        $this->purchaseTires(3, 76030);

        $report = app(\App\Services\ReportService::class)
            ->weeklyReport($this->admin, now()->startOfWeek(), now());

        $stockRow = collect($report['stock_counts'])->firstWhere('status', TireStatus::Stock->label());
        $this->assertNotNull($stockRow);
        $this->assertSame(3, $stockRow['total']);
        $this->assertCount(3, $report['stock_tires']);
    }
}
