<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\IncrementalDatabaseBackupJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule incremental database backup every 3 3 hosu// Temporarily set to every minute for testing
Schedule::job(new IncrementalDatabaseBackupJob)
    ->everyMinute()
    ->name('incremental-database-backup')
    ->withoutOverlapping()
    ->onOneServer();

