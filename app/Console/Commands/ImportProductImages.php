<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('products:import-images {folder : Папка с фото: имя файла — код 1С (РТ-00000805.jpg); РТ-00000805_2.jpg — дополнительное фото} {--force : Записать; без флага — только отчёт} {--min-size=300 : Минимальная сторона фото, px}')]
#[Description('Загрузить фото товаров из папки: файл называется кодом 1С; существующие фото не заменяются')]
class ImportProductImages extends Command
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Без --force ничего не пишет. Файл «КОД.jpg» становится главным фото товара с
     * этим кодом 1С, если у товара фото ещё нет; «КОД_2.jpg», «КОД_3.jpg» — фото
     * галереи. Файлы копируются в catalog/products (как при загрузке в админке).
     * Перед запуском: php artisan db:backup.
     */
    public function handle(): int
    {
        $folder = $this->argument('folder');

        if (! is_dir($folder)) {
            $this->error("Папка {$folder} не найдена.");

            return self::FAILURE;
        }

        $files = $this->files($folder);
        $products = Product::query()->whereIn('external_id', $files->pluck('code')->unique()->all())->get()->keyBy('external_id');
        $minSize = (int) $this->option('min-size');

        $report = ['main' => [], 'gallery' => [], 'skipped' => [], 'unknown' => [], 'small' => [], 'broken' => []];

        foreach ($files as $file) {
            $product = $products->get($file['code']);
            $size = @getimagesize($file['path']);

            if ($size === false) {
                $report['broken'][] = $file['name'];
            } elseif (min($size[0], $size[1]) < $minSize) {
                $report['small'][] = "{$file['name']} ({$size[0]}x{$size[1]})";
            } elseif ($product === null) {
                $report['unknown'][] = $file['name'];
            } elseif ($file['extra'] === null && filled($product->image)) {
                $report['skipped'][] = $file['name'];
            } else {
                $report[$file['extra'] === null ? 'main' : 'gallery'][] = $file + ['product' => $product];
            }
        }

        $this->components->twoColumnDetail('Файлов в папке', (string) $files->count());
        $this->components->twoColumnDetail('Главное фото получат', (string) count($report['main']));
        $this->components->twoColumnDetail('Фото в галерею', (string) count($report['gallery']));
        $this->components->twoColumnDetail('У товара уже есть фото (пропущены)', (string) count($report['skipped']));
        $this->components->twoColumnDetail('Нет товара с таким кодом 1С', (string) count($report['unknown']));
        $this->components->twoColumnDetail("Меньше {$minSize} px (пропущены)", (string) count($report['small']));
        $this->components->twoColumnDetail('Не картинка', (string) count($report['broken']));

        foreach (['unknown' => 'Нет товара', 'small' => 'Слишком маленькие', 'broken' => 'Повреждены'] as $key => $title) {
            if ($report[$key] !== []) {
                $this->line("{$title}: ".implode(', ', array_slice($report[$key], 0, 15)));
            }
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: фото не загружены. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        foreach ($report['main'] as $item) {
            $item['product']->update(['image' => $this->store($item['path'])]);
        }

        foreach ($report['gallery'] as $item) {
            $path = $this->store($item['path']);

            if (! $item['product']->images()->where('path', $path)->exists()) {
                $item['product']->images()->create(['path' => $path, 'sort_order' => $item['extra']]);
            }
        }

        $this->info('Загружено: главных фото — '.count($report['main']).', в галерею — '.count($report['gallery']).'.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{path: string, name: string, code: string, extra: ?int}>
     */
    private function files(string $folder): Collection
    {
        return collect(glob(rtrim($folder, '/').'/*') ?: [])
            ->filter(fn (string $path): bool => is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->sort()
            ->map(function (string $path): array {
                $base = pathinfo($path, PATHINFO_FILENAME);
                $isExtra = preg_match('/^(.+)_(\d+)$/u', $base, $match) === 1;

                return ['path' => $path, 'name' => basename($path), 'code' => $isExtra ? $match[1] : $base, 'extra' => $isExtra ? (int) $match[2] : null];
            })
            ->values();
    }

    /**
     * Копия файла в catalog/products под именем по содержимому: одинаковые
     * картинки не дублируются.
     */
    private function store(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $target = 'catalog/products/'.md5_file($path).'.'.($extension === 'jpeg' ? 'jpg' : $extension);

        if (! Storage::disk('public')->exists($target)) {
            Storage::disk('public')->putFileAs('catalog/products', new File($path), basename($target));
        }

        return $target;
    }
}
