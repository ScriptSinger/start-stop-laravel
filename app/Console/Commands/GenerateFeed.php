<?php

namespace App\Console\Commands;

use App\Services\Catalog\YandexFeed;
use App\Services\Catalog\YandexFeedReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('feed:generate {--path= : Куда записать фид (по умолчанию public/yml.xml)} {--report : Ничего не писать, а показать, какие включённые товары не попали в фид и почему}')]
#[Description('Собрать YML-фид для Яндекса (public/yml.xml): включённые товары в наличии и под заказ с ценой')]
class GenerateFeed extends Command
{
    /**
     * Фид пересобирается каждую ночь (routes/console.php): цена и наличие в нём
     * должны совпадать со страницей товара, иначе Яндекс отклонит товары.
     */
    public function handle(YandexFeed $feed, YandexFeedReport $report): int
    {
        if ($this->option('report')) {
            return $this->printReport($report);
        }

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

    private function printReport(YandexFeedReport $report): int
    {
        $data = $report->build();

        $this->info("Включённых товаров: {$data['total']}, в фиде: {$data['exported']}, отсечено: ".($data['total'] - $data['exported']));

        $this->table(['Причина (первая подходящая)', 'Товаров'], array_map(fn (array $reason): array => [$reason['label'], $reason['count']], $data['reasons']));

        foreach ($data['reasons'] as $reason) {
            if ($reason['examples'] === []) {
                continue;
            }

            $this->newLine();
            $this->line("<options=bold>{$reason['label']}</> — {$reason['count']}, примеры:");
            $this->table(['id', 'Название', 'Разделы'], array_map(fn (array $example): array => [$example['id'], $example['name'], $example['categories']], $reason['examples']));
        }

        $this->newLine();
        $this->line('<options=bold>По разделам</> (товар считается в каждом своём разделе):');
        $this->table(
            ['id', 'Раздел', 'Родитель', 'Разрешён', 'В фиде', 'Не попало'],
            array_map(fn (array $row): array => [$row['id'], $row['name'], $row['parent'], $row['allowed'] ? 'да' : 'нет', $row['exported'], $row['skipped']], $data['sections']),
        );

        if ($data['skippedRoots'] !== []) {
            $this->newLine();
            $this->warn('Товары отсечены из-за раздела вне shop.feed.*. Корневые разделы этих товаров (чтобы разрешить раздел с подразделами, добавьте его id в shop.feed.category_ids):');
            $this->table(['id', 'Корневой раздел', 'Товаров'], array_map(fn (array $row): array => [$row['id'], $row['name'], $row['products']], $data['skippedRoots']));
        }

        return self::SUCCESS;
    }
}
