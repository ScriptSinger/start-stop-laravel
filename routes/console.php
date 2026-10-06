<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ночной пересчёт посадочных «Аккумулятор для …» в кеш — до карты сайта, она их использует.
Schedule::command('car-landings:refresh')->dailyAt('03:00')->timezone('Asia/Yekaterinburg')->withoutOverlapping();

// Ночная пересборка public/sitemap.xml из базы (контейнер scheduler в docker-compose).
Schedule::command('sitemap:generate')->dailyAt('03:15')->timezone('Asia/Yekaterinburg')->withoutOverlapping();

// Ночная резервная копия базы с проверкой и ротацией (config/backup.php).
Schedule::command('db:backup')->dailyAt('02:30')->timezone('Asia/Yekaterinburg')->withoutOverlapping()
    ->onFailure(fn () => Log::critical('Ночная резервная копия базы не снята — см. вывод db:backup.'));
