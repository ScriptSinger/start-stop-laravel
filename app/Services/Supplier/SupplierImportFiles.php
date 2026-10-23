<?php

namespace App\Services\Supplier;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Загруженные в админке прайсы поставщиков: файл и рядом пометка (поставщик,
 * имя, когда загружен, применён ли). Диск local, чтобы отчёт открывался позже.
 */
class SupplierImportFiles
{
    private const DIRECTORY = 'supplier-imports';

    private const ID_PATTERN = '/^\d{8}-\d{6}-[a-f0-9]{6}$/';

    public function store(UploadedFile $file, int $supplierId): string
    {
        $id = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
        $extension = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        Storage::disk('local')->putFileAs(self::DIRECTORY, $file, "{$id}.{$extension}");
        $this->writeMeta($id, [
            'supplier_id' => $supplierId,
            'extension' => $extension,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /**
     * Полный путь к файлу или null, если такой загрузки нет (в том числе при
     * подозрительном идентификаторе).
     */
    public function path(string $id): ?string
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        $file = self::DIRECTORY."/{$id}.".($this->meta($id)['extension'] ?? 'csv');

        return Storage::disk('local')->exists($file) ? Storage::disk('local')->path($file) : null;
    }

    public function delete(string $id): void
    {
        Storage::disk('local')->delete(self::DIRECTORY."/{$id}.".($this->meta($id)['extension'] ?? 'csv'));
        Storage::disk('local')->delete($this->metaFile($id));
    }

    /**
     * @param  array<string, int>  $summary
     */
    public function markApplied(string $id, array $summary): void
    {
        $this->writeMeta($id, [...$this->meta($id), 'applied_at' => now()->toIso8601String(), 'summary' => $summary]);
    }

    /**
     * @return array{supplier_id?: int, extension?: string, original_name?: string, uploaded_at?: string, applied_at?: string, summary?: array<string, int>}
     */
    public function meta(string $id): array
    {
        $disk = Storage::disk('local');

        return $disk->exists($this->metaFile($id)) ? (array) json_decode((string) $disk->get($this->metaFile($id)), true) : [];
    }

    /**
     * Последние загрузки, новые сверху.
     *
     * @return Collection<int, array{id: string, original_name: string, supplier_id: ?int, uploaded_at: ?CarbonImmutable, applied_at: ?CarbonImmutable}>
     */
    public function recent(int $limit = 10): Collection
    {
        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $file): bool => str_ends_with($file, '.json'))
            ->map(fn (string $file): string => basename($file, '.json'))
            ->filter(fn (string $id): bool => (bool) preg_match(self::ID_PATTERN, $id))
            ->sortDesc()
            ->take($limit)
            ->map(function (string $id): array {
                $meta = $this->meta($id);

                return [
                    'id' => $id,
                    'original_name' => $meta['original_name'] ?? $id,
                    'supplier_id' => $meta['supplier_id'] ?? null,
                    'uploaded_at' => isset($meta['uploaded_at']) ? CarbonImmutable::parse($meta['uploaded_at']) : null,
                    'applied_at' => isset($meta['applied_at']) ? CarbonImmutable::parse($meta['applied_at']) : null,
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeMeta(string $id, array $meta): void
    {
        Storage::disk('local')->put($this->metaFile($id), (string) json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    private function metaFile(string $id): string
    {
        return self::DIRECTORY."/{$id}.json";
    }
}
