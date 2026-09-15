<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rodante:backup --keep=14')->dailyAt('03:15')->name('rodante-backup');
Schedule::command('rodante:integrity')->dailyAt('04:00')->name('rodante-integrity');
Schedule::command('rodante:weekly-report')
    ->weeklyOn(1, '08:00')
    ->when(fn () => (bool) config('rodante.weekly_report.enabled'))
    ->name('rodante-weekly-report');
