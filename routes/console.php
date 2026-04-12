<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check to run daily at midnight
Schedule::command('subscriptions:expire')->daily();

// Schedule renewal reminders to run daily at 9 AM
Schedule::command('subscriptions:send-reminders')->dailyAt('09:00');
