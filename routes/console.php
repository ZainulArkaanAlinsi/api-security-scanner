<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-scan monitored tickets every six hours (00:00, 06:00, 12:00, 18:00).
// --hours=2 so every slot re-scans the monitored tickets, while a ticket that
// was scanned manually minutes ago is skipped.
// Requires a scheduler: production -> cron "* * * * * php artisan schedule:run",
// locally -> php artisan schedule:work
Schedule::command('scan:due --hours=2')->everySixHours()->withoutOverlapping();
