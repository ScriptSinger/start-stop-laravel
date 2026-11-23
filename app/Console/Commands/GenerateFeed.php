<?php

namespace App\Console\Commands;

use App\Services\Catalog\YandexFeed;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('feed:generate {--path= : Куда записать фид (по умолчанию public/yml.xml)}')]
#[Description('Собрать YML-фид для Яндекса (public/yml.xml): включённые товары в наличии и под заказ с ценой')]
class GenerateFeed extends Command
{
    /**
     * Фид пересобирается каждую ночь (routes/console.php): цена и наличие в нём
     * должны совпадать со страницей товара, иначе Яндекс отклонит товары.
     */
    public function handle(YandexFeed $feed): int
    {
        $path = $this->option('path') ?: public_path('yml.xml');

        try {
            $count = $feed->store($path);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Фид не собран: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("yml.xml: товаров {$count} → {$path}");

        return self::SUCCESS;
    }
}
