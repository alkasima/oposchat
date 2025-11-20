<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription management tasks
Schedule::command('subscriptions:check-status --handle-expirations')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('subscriptions:sync --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('payments:handle-failed --notify')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('subscriptions:process-scheduled-changes')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
