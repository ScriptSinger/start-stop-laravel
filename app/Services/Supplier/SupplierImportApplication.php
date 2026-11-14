<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SupplierImportApplication
{
    public function __construct(
        private SupplierImportFiles $files,
        private SupplierPriceImporter $importer,
        private DatabaseBackup $backup,
    ) {}

    public function apply(string $importId, int $adminId, string $adminName): SupplierImportReport
    {
        Log::debug('[FIX:supplier-import] Applying import', ['import_id' => $importId, 'admin_id' => $adminId]);
        try {
            $path = $this->files->path($importId);
            if ($path === null) {
                throw new RuntimeException('Загрузка не найдена: загрузите файл заново.');
            }
            $supplierId = $this->files->meta($importId)['supplier_id'] ?? 0;

            $report = DB::transaction(function () use ($importId, $path, $supplierId, $adminId, $adminName): SupplierImportReport {
                $supplier = Supplier::query()->lockForUpdate()->find($supplierId);
                if ($supplier === null) {
                    throw new RuntimeException('Поставщик не найден.');
                }
                if (isset($this->files->meta($importId)['applied_at'])) {
                    throw new RuntimeException('Этот прайс уже применён. Для обновления загрузите новый файл.');
                }
                try {
                    $this->backup->create('before-supplier-import');
                } catch (RuntimeException $exception) {
                    throw new RuntimeException('Не применено: не удалось снять копию базы. '.$exception->getMessage(), previous: $exception);
                }
                $report = $this->importer->run($supplier, $path, apply: true);
                $this->files->markApplied($importId, ['created' => $report->created, 'added' => $report->productsAdded, 'repriced' => $report->productsRepriced, 'cleared' => $report->productsCleared], $adminId, $adminName);

                return $report;
            });
        } catch (Throwable $exception) {
            Log::debug('[FIX:supplier-import] Import failed', ['import_id' => $importId, 'exception_type' => $exception::class]);
            throw $exception;
        }
        Log::debug('[FIX:supplier-import] Import applied', ['import_id' => $importId]);

        return $report;
    }
}
