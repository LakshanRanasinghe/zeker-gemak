<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:generate-google-merchant-feed')->dailyAt('07:00');
Schedule::command('app:update-jaritech-stock')->dailyAt('04:00');
Schedule::command('app:update-s2b-stock')->dailyAt('04:30');
