<?php

namespace App\Services\Supplier;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SupplierOfferLinker
{
    public function __construct(
        private SupplierImportFiles $files,
        private SupplierPriceReader $reader,
        private SupplierLinkDrafts $drafts,
        private SupplierStockSync $sync,
        private DatabaseBackup $backup,
    ) {}

    public function select(string $importId, int $adminId, int $offerId, int $productId): void
    {
        $path = $this->files->path($importId);
        if ($path === null) {
            throw ValidationException::withMessages(['import' => 'Загрузка не найдена.']);
        }
        $meta = $this->files->meta($importId);
        if (! isset($meta['applied_at'])) {
            throw ValidationException::withMessages(['import' => 'Сначала примените прайс.']);
        }
        $supplier = Supplier::query()->find($meta['supplier_id']);
        $offer = $supplier?->offers()->where('in_price', true)->find($offerId);
        if ($offer === null) {
            throw ValidationException::withMessages(['offer_id' => 'Актуальное предложение этого поставщика не найдено.']);
        }
        $inFile = false;
        foreach ($this->reader->offers($supplier->format, $path) as $row) {
            if ($row['code'] === $offer->supplier_code) {
                $inFile = true;
                break;
            }
        }
        if (! $inFile) {
            throw ValidationException::withMessages(['offer_id' => 'Предложения нет в выбранном прайсе.']);
        }

        if ($offer->product_id !== null) {
            throw ValidationException::withMessages(['offer_id' => 'Предложение уже привязано. Обновите страницу.']);
        }
        if (! Product::query()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Товар не найден или удалён.']);
        }

        DB::transaction(function () use ($supplier, $importId, $adminId, $offerId, $productId): void {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $offer = $supplier->offers()->lockForUpdate()->find($offerId);
            $product = Product::query()->lockForUpdate()->find($productId);
            if ($offer === null || ! $offer->in_price || $offer->product_id !== null || $product === null) {
                throw ValidationException::withMessages(['offer_id' => 'Пара изменилась. Обновите страницу.']);
            }
            $this->drafts->put($importId, $adminId, $offerId, $productId);
        });
    }

    public function apply(string $importId, int $adminId): int
    {
        $path = $this->files->path($importId);
        if ($path === null) {
            throw ValidationException::withMessages(['import' => 'Загрузка не найдена.']);
        }
        $meta = $this->files->meta($importId);
        $supplier = isset($meta['applied_at']) ? Supplier::query()->find($meta['supplier_id'] ?? null) : null;
        if ($supplier === null) {
            throw ValidationException::withMessages(['import' => 'Сначала примените прайс.']);
        }
        $codes = [];
        foreach ($this->reader->offers($supplier->format, $path) as $row) {
            $codes[$row['code']] = true;
        }

        Log::debug('[FIX:supplier-links] Applying drafts', ['import_id' => $importId, 'admin_id' => $adminId]);
        try {
            $count = DB::transaction(function () use ($supplier, $importId, $adminId, $codes): int {
                Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                $drafts = $this->drafts->pending($importId, $adminId)->orderBy('id')->lockForUpdate()->get();
                if ($drafts->isEmpty()) {
                    throw ValidationException::withMessages(['import' => 'Добавьте предложения на проверку.']);
                }
                $offers = $supplier->offers()->whereIn('id', $drafts->pluck('offer_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $productIds = $drafts->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
                $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($drafts as $draft) {
                    $offer = $offers->get($draft->offer_id);
                    if ($offer === null || ! $offer->in_price || $offer->product_id !== null || ! isset($codes[$offer->supplier_code]) || ! $products->has($draft->product_id)) {
                        throw ValidationException::withMessages(['import' => 'Одна из пар изменилась или стала недоступна. Ничего не сохранено: проверьте список.']);
                    }
                }
                try {
                    $this->backup->create('before-supplier-links');
                } catch (RuntimeException $exception) {
                    throw new RuntimeException('Не применено: не удалось снять копию базы. '.$exception->getMessage(), previous: $exception);
                }
                foreach ($drafts as $draft) {
                    $offers->get($draft->offer_id)->update(['product_id' => $draft->product_id]);
                }
                $this->sync->syncProducts($productIds);
                $this->drafts->pending($importId, $adminId)->whereIn('id', $drafts->pluck('id'))->update(['applied_at' => now(), 'updated_at' => now()]);

                return $drafts->count();
            });
        } catch (\Throwable $exception) {
            Log::debug('[FIX:supplier-links] Draft application failed', ['import_id' => $importId, 'exception_type' => $exception::class]);
            throw $exception;
        }
        Log::debug('[FIX:supplier-links] Drafts applied', ['import_id' => $importId, 'count' => $count]);

        return $count;
    }
}
