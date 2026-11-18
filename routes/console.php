<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Tampilkan kutipan motivasi');

Schedule::command('clipflow:dispatch-uploads')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('clipflow:reconcile-uploads')
    ->everyTenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('clipflow:heartbeat')
    ->everyMinute()
    ->runInBackground();

Schedule::command('clipflow:prune')
    ->daily()
    ->at('01:30')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('queue:prune-failed')
    ->weekly()
    ->runInBackground();
