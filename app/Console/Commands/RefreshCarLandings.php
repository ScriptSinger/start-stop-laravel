<?php

namespace App\Console\Commands;

use App\Services\CarLanding\CarLandingCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('car-landings:refresh')]
#[Description('Пересчитать посадочные «Аккумулятор для …» (модели, поколения, подходящие товары) и положить в кеш')]
class RefreshCarLandings extends Command
{
    /**
     * Ночью перед sitemap:generate (routes/console.php) и вручную — после
     * импорта товаров или включения новой марки в shop.car_landings.
     */
    public function handle(CarLandingCatalog $catalog): int
    {
        $pages = $catalog->refresh();

        $this->info("Посадочные страницы пересчитаны: {$pages}");

        return self::SUCCESS;
    }
}
