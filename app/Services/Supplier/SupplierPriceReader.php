<?php

namespace App\Services\Supplier;

use App\Enums\SupplierPriceFormat;
use App\Services\Import\XlsxReader;
use Generator;
use RuntimeException;

/**
 * Читает прайс поставщика (.xlsx или .csv) и отдаёт позиции в одном виде:
 * код (или нормализованное название, если кода в прайсе нет), название,
 * закупочная цена и остаток (null — в прайсе остатков нет).
 *
 * Старый .xls надо пересохранить как .xlsx: читать его без LibreOffice нечем.
 */
class SupplierPriceReader
{
    public function __construct(private XlsxReader $xlsx) {}

    /**
     * @return Generator<int, array{code: string, name: string, cost: float, quantity: ?int}>
     */
    public function offers(SupplierPriceFormat $format, string $path): Generator
    {
        foreach ($this->rows($path) as $row) {
            $offer = match ($format) {
                SupplierPriceFormat::AkbAuto => $this->akbAuto($row),
                SupplierPriceFormat::SibElectro => $this->sibElectro($row),
                SupplierPriceFormat::Drilling => $this->drilling($row),
            };

            if ($offer !== null) {
                yield $offer;
            }
        }
    }

    /**
     * Название без лишних пробелов и в нижнем регистре — одинаково в прайсе
     * и в файле-посреднике.
     */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $name))));
    }

    /**
     * «6 850,00», «7789.6000000000013», «1 220» → число; всё прочее → null.
     */
    public static function parseNumber(?string $value): ?float
    {
        $clean = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function rows(string $path): Generator
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx') {
            yield from $this->xlsx->rows($path);

            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Не удалось открыть {$path}.");
        }

        while (($cells = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            yield collect($cells)->mapWithKeys(fn (?string $value, int $index): array => [self::columnLetter($index) => (string) $value])->all();
        }

        fclose($handle);
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';

        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $letter = chr(65 + ($number - 1) % 26).$letter;
        }

        return $letter;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{code: string, name: string, cost: float, quantity: ?int}|null
     */
    private function akbAuto(array $row): ?array
    {
        $code = trim($row['A'] ?? '');
        $cost = self::parseNumber($row['S'] ?? null);

        if (! str_starts_with($code, 'УТ-') || $cost === null) {
            return null;
        }

        return ['code' => $code, 'name' => trim($row['M'] ?? ''), 'cost' => $cost, 'quantity' => (int) self::parseNumber($row['T'] ?? null)];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{code: string, name: string, cost: float, quantity: ?int}|null
     */
    private function sibElectro(array $row): ?array
    {
        $name = trim($row['B'] ?? '');
        $cost = self::parseNumber($row['H'] ?? null);

        if ($name === '' || $cost === null) {
            return null;
        }

        return ['code' => self::normalizeName($name), 'name' => $name, 'cost' => $cost, 'quantity' => max((int) self::parseNumber($row['C'] ?? null), 0)];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{code: string, name: string, cost: float, quantity: ?int}|null
     */
    private function drilling(array $row): ?array
    {
        $name = trim($row['A'] ?? '');
        $cost = self::parseNumber($row['C'] ?? null);

        if ($name === '' || $cost === null) {
            return null;
        }

        return ['code' => self::normalizeName($name), 'name' => $name, 'cost' => $cost, 'quantity' => null];
    }
}
