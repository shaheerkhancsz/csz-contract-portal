<?php

use Illuminate\Support\Facades\Schedule;

// cPanel cron (every minute): php /home/USER/contracts/artisan schedule:run
Schedule::command('contracts:refresh-statuses')->dailyAt('00:10');

// Chase clients who were sent a contract but haven't signed yet.
Schedule::command('contracts:signing-reminders')->dailyAt('08:00');

// Drain the database queue (contract emails are queued). --stop-when-empty keeps it
// short-lived so the per-minute cron can relaunch it; withoutOverlapping avoids pile-ups.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
