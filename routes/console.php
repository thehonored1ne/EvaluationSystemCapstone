<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use Illuminate\Support\Facades\Schedule;

// Automatically retrain the AI model daily at midnight
Schedule::command('ai:train')->daily();

// Automatically check evaluation deadlines and dispatch reminders hourly
Schedule::command('evaluations:send-reminders')->hourly()->withoutOverlapping();
