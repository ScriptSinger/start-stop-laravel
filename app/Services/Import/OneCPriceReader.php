<?php

namespace App\Services\Import;

use InvalidArgumentException;
use RuntimeException;

/**
 * Чтение прайса, выгруженного из 1С или «Торгового дома». Формат определяется
 * по заголовку:
 *  - «Код;Артикул;Наименование;Свободный остаток;Розничная цена» — выгрузка 1С
 *    (UTF-8 с BOM, точка в числах, концы строк смешанные);
 *  - «id;rubrika;artikul;…;zagolovok;brand;price;kolvo;…» — «Торговый дом»
 *    (cp1251, запятая в числах).
 * Кодировка определяется по содержимому. Пустая цена и пустой остаток отдаются
 * как null — что с ними делать, решает импорт.
 */
class OneCPriceReader
{
    public const FORMAT_ONEC = '1С («Код;Артикул;Наименование…»)';

    public const FORMAT_TRADE_HOUSE = 'Торговый дом («id;rubrika;…»)';

    /**
     * @return array{format: string, rows: list<array{external_id: string, name: string, article: string, price: ?float, quantity: ?float}>}
     */
    public function read(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Не удалось прочитать {$path}.");
        }

        $lines = $this->lines($this->toUtf8($contents));
        $header = array_map(fn (string $title): string => mb_strtolower(trim($title)), str_getcsv((string) array_shift($lines), ';', '"', ''));

        [$format, $columns] = $this->columns($header);

        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv($line, ';', '"', '');
            $externalId = trim($cells[$columns['external_id']] ?? '');

            if ($externalId === '') {
                continue;
            }

            $rows[] = [
                'external_id' => $externalId,
                'name' => trim((string) preg_replace('/\s+/u', ' ', $cells[$columns['name']] ?? '')),
                'article' => trim($cells[$columns['article']] ?? ''),
                'price' => $this->number($cells[$columns['price']] ?? ''),
                'quantity' => $this->number($cells[$columns['quantity']] ?? ''),
            ];
        }

        return ['format' => $format, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $header
     * @return array{0: string, 1: array{external_id: int, name: int, article: int, price: int, quantity: int}}
     */
    private function columns(array $header): array
    {
        if (($header[0] ?? '') === 'код') {
            return [self::FORMAT_ONEC, $this->indexes($header, ['external_id' => 'код', 'name' => 'наименование', 'article' => 'артикул', 'price' => 'розничная цена', 'quantity' => 'свободный остаток'])];
        }

        if (($header[0] ?? '') === 'id') {
            return [self::FORMAT_TRADE_HOUSE, $this->indexes($header, ['external_id' => 'id', 'name' => 'zagolovok', 'article' => 'artikul', 'price' => 'price', 'quantity' => 'kolvo'])];
        }

        throw new InvalidArgumentException('Неизвестный формат файла: ожидается заголовок «Код;Артикул;…» (1С) или «id;rubrika;…» (Торговый дом).');
    }

    /**
     * @param  list<string>  $header
     * @param  array<string, string>  $titles
     * @return array{external_id: int, name: int, article: int, price: int, quantity: int}
     */
    private function indexes(array $header, array $titles): array
    {
        $indexes = [];

        foreach ($titles as $field => $title) {
            $index = array_search($title, $header, true);

            if ($index === false) {
                throw new InvalidArgumentException("В заголовке файла нет колонки «{$title}».");
            }

            $indexes[$field] = $index;
        }

        return $indexes;
    }

    private function toUtf8(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        return mb_check_encoding($contents, 'UTF-8') ? $contents : mb_convert_encoding($contents, 'UTF-8', 'Windows-1251');
    }

    /**
     * Концы строк в выгрузках смешанные (CRLF, одиночный CR), пустые строки между
     * записями пропускаются.
     *
     * @return list<string>
     */
    private function lines(string $contents): array
    {
        return array_values(array_filter(preg_split('/\R/u', $contents), fn (string $line): bool => trim($line) !== ''));
    }

    private function number(string $value): ?float
    {
        $value = str_replace(["\u{A0}", ' '], '', trim($value));

        if ($value === '') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }
}
