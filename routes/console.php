<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the order status sync command
Schedule::command('orders:sync-status')->everyFiveMinutes();

// Complete bigtime and telecel orders older than 30 minutes
Schedule::command('orders:complete-old')->everyTenMinutes();

// Sync Jesco order statuses
Schedule::command('orders:sync-jesco-status')->everyTenMinutes();

// Sync EasyData order statuses
Schedule::command('orders:sync-easydata-status')->everyTenMinutes();
