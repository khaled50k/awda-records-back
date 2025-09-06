<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\IncrementalDatabaseBackupJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule incremental database backup every 3 hours
Schedule::job(new IncrementalDatabaseBackupJob)
    ->everyThreeHours()
    ->name('incremental-database-backup')
    ->withoutOverlapping()
    ->onOneServer();
