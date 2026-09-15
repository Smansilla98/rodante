<?php

namespace Tests\Feature;

use App\Notifications\WeeklyReportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesDomain;
use Tests\TestCase;

class WeeklyReportCommandTest extends TestCase
{
    use CreatesDomain;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDomain();
        $this->admin->forceFill(['email' => 'jefe@ejemplo.test'])->save();
    }

    public function test_command_is_noop_when_disabled(): void
    {
        config(['rodante.weekly_report.enabled' => false]);

        $this->artisan('rodante:weekly-report')
            ->expectsOutputToContain('desactivado')
            ->assertSuccessful();
    }

    public function test_dry_run_lists_recipients_without_sending(): void
    {
        config([
            'rodante.weekly_report.enabled' => true,
            'rodante.weekly_report.emails' => [],
        ]);
        Notification::fake();
        $this->purchaseTires(1, 76200);

        $this->artisan('rodante:weekly-report --dry-run')
            ->expectsOutputToContain('jefe@ejemplo.test')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_sends_notifications_when_enabled(): void
    {
        config([
            'rodante.weekly_report.enabled' => true,
            'rodante.weekly_report.emails' => ['informe@ejemplo.test'],
        ]);
        Notification::fake();
        $this->purchaseTires(1, 76210);

        $this->artisan('rodante:weekly-report')
            ->expectsOutputToContain('Enviados: 1')
            ->assertSuccessful();

        Notification::assertSentOnDemand(WeeklyReportNotification::class);
    }
}
