<?php

namespace App\Services\Supplier;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Последний выбор сотрудника; повторный выбор открывает новый черновик. */
class SupplierLinkDrafts
{
    public function pending(string $importId, int $adminId): Builder
    {
        return DB::table('supplier_link_drafts')->where('import_id', $importId)
            ->where('admin_id', $adminId)->whereNull('applied_at');
    }

    /** @return array<int, int> */
    public function selections(string $importId, int $adminId): array
    {
        return $this->pending($importId, $adminId)->orderBy('id')->pluck('product_id', 'offer_id')
            ->map(fn ($id): int => (int) $id)->all();
    }

    public function put(string $importId, int $adminId, int $offerId, int $productId): void
    {
        DB::table('supplier_link_drafts')->upsert([
            'import_id' => $importId, 'admin_id' => $adminId, 'offer_id' => $offerId,
            'product_id' => $productId, 'applied_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ], ['import_id', 'admin_id', 'offer_id'], ['product_id', 'applied_at', 'updated_at']);
    }

    public function remove(string $importId, int $adminId, int $offerId): void
    {
        $this->pending($importId, $adminId)->where('offer_id', $offerId)->delete();
    }
}
