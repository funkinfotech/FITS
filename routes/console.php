<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:mark-overdue')->dailyAt('01:00');
Schedule::command('invoices:generate-recurring')->dailyAt('01:15');
Schedule::command('invoices:notify-admins-overdue')->dailyAt('01:30');
