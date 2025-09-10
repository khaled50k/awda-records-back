<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\IncrementalDatabaseBackupJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule incremental database backup every 3 hours
Schedule::command('backup:incremental --sync')
    ->cron('0 */3 * * *')
    ->name('incremental-database-backup')
    ->withoutOverlapping()
    ->onOneServer();

