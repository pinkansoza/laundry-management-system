<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan foto pemesanan yang sudah lebih dari 2 bulan
// Jalan otomatis setiap hari jam 2 pagi
Schedule::command('photos:clean-old')->dailyAt('02:00');
