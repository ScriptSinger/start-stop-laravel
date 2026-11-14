<?php

namespace App\Services\Import;

use App\Models\Product;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OneCModeration
{
    public function __construct(private OneCImportFiles $files, private OneCPriceImporter $importer, private OneCPriceReader $reader, private DatabaseBackup $backup) {}

    public function drafts(string $id, int $admin): Builder
    {
        return DB::table('onec_link_drafts')->where('import_id', $id)->where('admin_id', $admin);
    }

    public function rows(string $id): array
    {
        $path = $this->files->path($id);
        if ($path === null) {
            throw new RuntimeException('Загрузка не найдена.');
        }

        return collect($this->reader->read($path)['rows'])->keyBy('external_id')->all();
    }

    private function lock(): void
    {
        DB::table('onec_import_lock')->where('id', 1)->lockForUpdate()->first();
    }

    public function apply(string $id, int $admin, string $name): int
    {
        return DB::transaction(function () use ($id, $admin, $name): int {
            $this->lock();
            $rows = $this->rows($id);
            if (isset($this->files->meta($id)['applied_at'])) {
                throw new RuntimeException('Этот прайс уже применён. Загрузите новый файл.');
            }
            if ($rows === []) {
                throw new RuntimeException('В файле нет распознанных позиций.');
            }
            $this->backup->create('before-onec-import');
            $count = $this->importer->apply($this->importer->preview($this->files->path($id)));
            $this->files->markApplied($id, $count, $admin, $name);

            return $count;
        });
    }

    private function validatePair(string $code, int $productId): Product
    {
        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product === null || filled($product->external_id) || Product::withTrashed()->where('external_id', $code)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Пара недоступна: товар удалён, уже имеет код 1С или код занят другим товаром.']);
        }

        return $product;
    }

    public function select(string $id, int $admin, string $code, int $productId): void
    {
        DB::transaction(function () use ($id, $admin, $code, $productId): void {
            $this->lock();
            $rows = $this->rows($id);
            if (! isset($this->files->meta($id)['applied_at']) || ! isset($rows[$code])) {
                throw ValidationException::withMessages(['import' => 'Сначала примените прайс и выберите позицию из этого файла.']);
            }
            $this->validatePair($code, $productId);
            if ($this->drafts($id, $admin)->where('code', '!=', $code)->where('product_id', $productId)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Этот товар уже выбран для другой позиции.']);
            }
            DB::table('onec_link_drafts')->upsert(['import_id' => $id, 'admin_id' => $admin, 'code' => $code, 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now()], ['import_id', 'admin_id', 'code'], ['product_id', 'updated_at']);
        });
    }

    public function applyDrafts(string $id, int $admin): int
    {
        return DB::transaction(function () use ($id, $admin): int {
            $this->lock();
            $rows = $this->rows($id);
            $drafts = $this->drafts($id, $admin)->orderBy('product_id')->lockForUpdate()->get();
            if (! isset($this->files->meta($id)['applied_at']) || $drafts->isEmpty()) {
                throw ValidationException::withMessages(['import' => 'Примените прайс и добавьте позиции на проверку.']);
            }
            $products = [];
            foreach ($drafts as $draft) {
                if (! isset($rows[$draft->code])) {
                    throw ValidationException::withMessages(['import' => 'Позиция отсутствует в файле.']);
                } $products[$draft->id] = $this->validatePair($draft->code, $draft->product_id);
            }
            $this->backup->create('before-onec-links');
            foreach ($drafts as $draft) {
                $row = $rows[$draft->code];
                $attributes = ['external_id' => $draft->code, 'quantity' => max(0, (int) round($row['quantity'] ?? 0))];
                if (($row['price'] ?? 0) > 0) {
                    $attributes['price'] = $row['price'];
                }$products[$draft->id]->update($attributes);
            }
            $this->drafts($id, $admin)->whereIn('id', $drafts->pluck('id'))->delete();

            return $drafts->count();
        });
    }
}
