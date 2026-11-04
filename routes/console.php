<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Deletes expired TV sign-in codes (DeviceLogin::prunable).
Schedule::command('model:prune')->daily();
// Deletes API tokens expired for more than a day (config/sanctum.php: 180 days).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
