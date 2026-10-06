<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ночная пересборка public/sitemap.xml из базы (контейнер scheduler в docker-compose).
Schedule::command('sitemap:generate')->dailyAt('03:15')->timezone('Asia/Yekaterinburg')->withoutOverlapping();
