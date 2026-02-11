<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sincronizza:clienti --lookback=1')
    ->cron('*/2 * * * *')
    ->withoutOverlapping();

Schedule::command('mail-queue:process --limit=25')
    ->everyMinute()
    ->withoutOverlapping();
