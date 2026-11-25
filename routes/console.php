<?php

use Illuminate\Support\Facades\Schedule;

// cPanel cron (every minute): php /home/USER/contracts/artisan schedule:run
Schedule::command('contracts:refresh-statuses')->dailyAt('00:10');
