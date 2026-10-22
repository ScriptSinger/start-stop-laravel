<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Загруженные в админке прайсы 1С: файл и рядом пометка (имя, когда загружен,
 * применён ли). Хранятся на диске local, чтобы отчёт можно было открыть позже.
 */
class OneCImportFiles
{
    private const DIRECTORY = 'onec-imports';

    private const ID_PATTERN = '/^\d{8}-\d{6}-[a-f0-9]{6}$/';

    public function store(UploadedFile $file): string
    {
        $id = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));

        Storage::disk('local')->putFileAs(self::DIRECTORY, $file, "{$id}.csv");
        $this->writeMeta($id, ['original_name' => $file->getClientOriginalName(), 'uploaded_at' => now()->toIso8601String()]);

        return $id;
    }

    /**
     * Полный путь к файлу или null, если такой загрузки нет (в том числе при
     * подозрительном идентификаторе).
     */
    public function path(string $id): ?string
    {
        if (! preg_match(self::ID_PATTERN, $id) || ! Storage::disk('local')->exists($this->file($id))) {
            return null;
        }

        return Storage::disk('local')->path($this->file($id));
    }

    public function delete(string $id): void
    {
        Storage::disk('local')->delete([$this->file($id), $this->metaFile($id)]);
    }

    public function markApplied(string $id, int $changed): void
    {
        $this->writeMeta($id, [...$this->meta($id), 'applied_at' => now()->toIso8601String(), 'applied_count' => $changed]);
    }

    /**
     * @return array{original_name?: string, uploaded_at?: string, applied_at?: string, applied_count?: int}
     */
    public function meta(string $id): array
    {
        $disk = Storage::disk('local');

        return $disk->exists($this->metaFile($id)) ? (array) json_decode((string) $disk->get($this->metaFile($id)), true) : [];
    }

    /**
     * Последние загрузки, новые сверху.
     *
     * @return Collection<int, array{id: string, original_name: string, uploaded_at: ?CarbonImmutable, applied_at: ?CarbonImmutable, applied_count: ?int}>
     */
    public function recent(int $limit = 10): Collection
    {
        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $file): bool => str_ends_with($file, '.csv'))
            ->map(fn (string $file): string => basename($file, '.csv'))
            ->filter(fn (string $id): bool => (bool) preg_match(self::ID_PATTERN, $id))
            ->sortDesc()
            ->take($limit)
            ->map(function (string $id): array {
                $meta = $this->meta($id);

                return [
                    'id' => $id,
                    'original_name' => $meta['original_name'] ?? "{$id}.csv",
                    'uploaded_at' => isset($meta['uploaded_at']) ? CarbonImmutable::parse($meta['uploaded_at']) : null,
                    'applied_at' => isset($meta['applied_at']) ? CarbonImmutable::parse($meta['applied_at']) : null,
                    'applied_count' => $meta['applied_count'] ?? null,
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

    private function file(string $id): string
    {
        return self::DIRECTORY."/{$id}.csv";
    }

    private function metaFile(string $id): string
    {
        return self::DIRECTORY."/{$id}.json";
    }
}
