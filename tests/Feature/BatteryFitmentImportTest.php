<?php

namespace Tests\Feature;

use App\Models\BatteryFitment;
use App\Services\BatteryFitments\PodborRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BatteryFitmentImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['A' => ' ', 'B' => '#NAME?', 'C' => 'Категории 2', 'D' => 'Категории 3', 'E' => 'Категории 4', 'H' => 'Емкость 1', 'K' => 'Полярность 1', 'N' => 'Габариты 1', 'T' => 'Тип клемм'];

    public function test_row_strips_brand_and_model_from_generation_and_engine(): void
    {
        $row = PodborRow::parse([
            'A' => 'Аккумуляторы для ВАЗ (Lada) Vesta I 2015 - 2022 1.6 (114 л.с.) бензин',
            'B' => 'ВАЗ (Lada)',
            'C' => 'Vesta',
            'D' => 'I 2015 - 2022',
            'E' => 'ВАЗ (Lada) Vesta I 2015 - 2022 1.6 (114 л.с.) бензин',
            'H' => ' 60 Ач',
            'I' => ' 55 Ач',
            'K' => "\n Обратная [- +]",
            'L' => "\n Универсальная",
            'N' => ' 242x175x190                            ',
            'O' => ' 242x175x175 ',
            'T' => "\n <td>Европейский (клеммы стандартные, утоплены)",
        ]);

        $this->assertSame([
            'brand' => 'ВАЗ (Lada)',
            'model' => 'Vesta',
            'generation' => 'I 2015 - 2022',
            'engine' => '1.6 (114 л.с.) бензин',
            'capacity' => '60 Ач, 55 Ач',
            'polarity' => 'Обратная, Универсальная',
            'dims' => '242x175x190, 242x175x175',
            'terminals' => 'standard',
            'fixes' => [],
        ], $row);

        // Строка поколения: в D полное имя с маркой и моделью.
        $this->assertSame('I 2015 - 2022', PodborRow::parse(['A' => 'x', 'B' => 'ВАЗ (Lada)', 'C' => 'Vesta', 'D' => 'ВАЗ (Lada) Vesta I 2015 - 2022'])['generation']);
        // Строка модели: в C модель с маркой.
        $this->assertSame('Vesta', PodborRow::parse(['A' => 'x', 'B' => 'ВАЗ (Lada)', 'C' => 'ВАЗ (Lada) Vesta'])['model']);
    }

    public function test_row_repairs_excel_damage(): void
    {
        // «9-3» Excel сохранил как дату 9 марта — число 45360.
        $saab = PodborRow::parse(['A' => 'Аккумуляторы для Saab 9-3 II 2002 - 2008', 'B' => 'Saab', 'C' => '45360', 'D' => 'Saab 9-3 II 2002 - 2008']);
        $this->assertSame(['9-3', 'II 2002 - 2008', ['excel-date']], [$saab['model'], $saab['generation'], $saab['fixes']]);

        // Сломанная формула в ячейке марки.
        $cobra = PodborRow::parse(['A' => 'Аккумуляторы для AC Cobra', 'B' => '#NAME?', 'C' => 'AC Cobra']);
        $this->assertSame(['AC', 'Cobra', ['brand']], [$cobra['brand'], $cobra['model'], $cobra['fixes']]);

        // Узкий пробел в годах (в старой базе стал «?»).
        $this->assertSame('II Рестайлинг 2015 – 2018', PodborRow::parse(['A' => 'x', 'B' => 'Kia', 'C' => 'Ceed', 'D' => "II Рестайлинг 2015\u{2009}– 2018"])['generation']);

        $this->assertNull(PodborRow::parse(['A' => 'Аккумуляторы для ']));
    }

    public function test_row_maps_terminal_types(): void
    {
        $terminals = fn (string $type): ?string => PodborRow::parse(['A' => 'x', 'B' => 'Ford', 'C' => 'F-150', 'T' => $type])['terminals'];

        $this->assertSame('thin', $terminals('Азиатский (клеммы узкие, выступают над верхней крышкой)'));
        $this->assertSame('side', $terminals('Американский (боковые клеммы под болт)'));
        $this->assertSame('threaded', $terminals('Американский (клеммы УЗКИЕ под гайку, выступают над верхней крышкой)'));
        $this->assertSame('bolt', $terminals('Грузовые (клеммы под болт, выступают над верхней крышкой)'));
        $this->assertSame('standard', $terminals('Грузовые (клеммы под конус, выступают над верхней крышкой)'));
        $this->assertNull($terminals(''));
    }

    public function test_dry_run_reports_without_changing_database(): void
    {
        BatteryFitment::query()->create(['brand' => 'Старая', 'model' => 'Запись']);

        $this->artisan('battery:import-fitments', ['file' => $this->xlsx()])
            ->expectsOutputToContain('Пробный прогон: база не изменена.')
            ->assertSuccessful();

        $this->assertSame(['Старая'], BatteryFitment::query()->pluck('brand')->all());
    }

    public function test_force_replaces_table_with_file_contents(): void
    {
        BatteryFitment::query()->create(['brand' => 'Старая', 'model' => 'Запись']);

        $this->artisan('battery:import-fitments', ['file' => $this->xlsx(), '--force' => true])
            ->expectsOutputToContain('Записано машин: 2.')
            ->assertSuccessful();

        $this->assertSame(
            [['ВАЗ (Lada)', 'Vesta', null, null, 'Обратная'], ['ВАЗ (Lada)', 'Vesta', 'I 2015 - 2022', '1.6 (114 л.с.) бензин', 'Обратная']],
            BatteryFitment::query()->orderBy('id')->get()->map(fn (BatteryFitment $fitment): array => [$fitment->brand, $fitment->model, $fitment->generation, $fitment->engine, $fitment->polarity])->all(),
        );
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('battery:import-fitments', ['file' => '/nope.xlsx'])->assertFailed();
    }

    /**
     * Минимальный .xlsx: заголовок, две машины и пустая строка.
     */
    private function xlsx(): string
    {
        $rows = [
            self::HEADER,
            ['A' => 'Аккумуляторы для ВАЗ (Lada) Vesta', 'B' => 'ВАЗ (Lada)', 'C' => 'ВАЗ (Lada) Vesta', 'H' => '60 Ач', 'K' => 'Обратная [- +]', 'N' => '242x175x190', 'T' => '<td>Европейский (клеммы стандартные, утоплены)'],
            ['A' => 'Аккумуляторы для ', 'B' => '', 'C' => ''],
            ['A' => 'Аккумуляторы для ВАЗ (Lada) Vesta I 2015 - 2022 1.6 (114 л.с.) бензин', 'B' => 'ВАЗ (Lada)', 'C' => 'Vesta', 'D' => 'I 2015 - 2022', 'E' => 'ВАЗ (Lada) Vesta I 2015 - 2022 1.6 (114 л.с.) бензин', 'H' => '60 Ач', 'K' => 'Обратная [- +]', 'N' => '242x175x190', 'T' => '<td>Европейский (клеммы стандартные, утоплены)'],
        ];

        $strings = [];
        $sheetRows = '';

        foreach ($rows as $number => $cells) {
            $sheetRows .= '<row r="'.($number + 1).'">';

            foreach ($cells as $column => $value) {
                $strings[] = $value;
                $sheetRows .= '<c r="'.$column.($number + 1).'" t="s"><v>'.(count($strings) - 1).'</v></c>';
            }

            $sheetRows .= '</row>';
        }

        $path = storage_path('framework/testing/podbor-'.uniqid().'.xlsx');
        @mkdir(dirname($path), recursive: true);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn (string $value): string => '<si><t xml:space="preserve">'.htmlspecialchars($value, ENT_XML1).'</t></si>', $strings)).'</sst>');
        $zip->close();

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
