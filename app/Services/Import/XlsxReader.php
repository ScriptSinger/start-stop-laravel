<?php

namespace App\Services\Import;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Чтение первого листа .xlsx без сторонних пакетов: xlsx — это zip с XML.
 * Строки отдаются по одной (лист читается потоком), ячейки — по букве
 * колонки: ['A' => '…', 'B' => '…']. Формулы не вычисляются — берётся
 * сохранённое значение.
 */
class XlsxReader
{
    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $path): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Не удалось открыть {$path} как xlsx.");
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet === false) {
            throw new RuntimeException('В файле нет первого листа.');
        }

        $reader = XMLReader::XML($sheet, null, LIBXML_NONET | LIBXML_COMPACT);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                yield $this->row(simplexml_load_string($reader->readOuterXml()), $sharedStrings);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach (simplexml_load_string($xml, options: LIBXML_NONET)->si as $item) {
            // Текст бывает разбит на куски с разным оформлением (<r><t>…</t></r>).
            $strings[] = isset($item->t) ? (string) $item->t : implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<string, string>
     */
    private function row(\SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];

        foreach ($row->c as $cell) {
            $column = preg_replace('/\d+/', '', (string) $cell['r']);
            $type = (string) $cell['t'];

            $cells[$column] = match ($type) {
                's' => $sharedStrings[(int) $cell->v] ?? '',
                'inlineStr' => (string) $cell->is->t,
                default => (string) $cell->v,
            };
        }

        return $cells;
    }
}
