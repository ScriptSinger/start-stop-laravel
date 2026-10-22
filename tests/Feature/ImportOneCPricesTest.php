<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Import\OneCPriceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOneCPricesTest extends TestCase
{
    use RefreshDatabase;

    private const ONEC_HEADER = 'Код;Артикул;Наименование;Свободный остаток;Розничная цена';

    private const TRADE_HOUSE_HEADER = 'id;rubrika;artikul;artikul2;zagolovok;brand;price;kolvo;sht;artikul3 ;razdel ';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    public function test_reads_onec_format_with_bom_and_mixed_line_endings(): void
    {
        $path = $this->file("\xEF\xBB\xBF".self::ONEC_HEADER."\r\r\n"
            .'228;;"Аккумулятор  AKOM 60Ah   ";2.000;7300.00'."\r\r\n"
            .'375;;"Пылесос";;'."\r\r\n");

        $file = (new OneCPriceReader)->read($path);

        $this->assertSame(OneCPriceReader::FORMAT_ONEC, $file['format']);
        $this->assertSame([
            ['external_id' => '228', 'name' => 'Аккумулятор AKOM 60Ah', 'article' => '', 'price' => 7300.0, 'quantity' => 2.0],
            ['external_id' => '375', 'name' => 'Пылесос', 'article' => '', 'price' => null, 'quantity' => null],
        ], $file['rows']);
    }

    public function test_reads_trade_house_format_in_cp1251_with_decimal_comma(): void
    {
        $path = $this->file(mb_convert_encoding(self::TRADE_HOUSE_HEADER."\r\n"
            .'380;"Инструмент";"";"";"Avisteel набор головок";"Av";1350,00;1,0000;"шт";"";"1"'."\r\n", 'Windows-1251', 'UTF-8'));

        $file = (new OneCPriceReader)->read($path);

        $this->assertSame(OneCPriceReader::FORMAT_TRADE_HOUSE, $file['format']);
        $this->assertSame([['external_id' => '380', 'name' => 'Avisteel набор головок', 'article' => '', 'price' => 1350.0, 'quantity' => 1.0]], $file['rows']);
    }

    public function test_dry_run_does_not_change_database(): void
    {
        $product = $this->product('akom', ['external_id' => '228', 'price' => 6900, 'quantity' => 1]);
        $path = $this->onecFile(['228;;"AKOM";5.000;7300.00']);

        $this->artisan('import:onec', ['file' => $path])
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame(6900.0, (float) $product->fresh()->price);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_force_updates_price_and_quantity_but_not_name(): void
    {
        $product = $this->product('akom', ['name' => 'AKOM 60 Ah О.П.', 'external_id' => '228', 'price' => 6900, 'quantity' => 1]);
        $path = $this->onecFile(['228;;"Аккумулятор AKOM 60Ah о.п. 540A L2L";5.000;7300.00']);

        $this->artisan('import:onec', ['file' => $path, '--force' => true])->assertSuccessful();

        $product->refresh();
        $this->assertSame(7300.0, (float) $product->price);
        $this->assertSame(5, $product->quantity);
        $this->assertSame('AKOM 60 Ah О.П.', $product->name);
    }

    public function test_empty_price_keeps_price_and_empty_quantity_means_zero(): void
    {
        $product = $this->product('akom', ['external_id' => '228', 'price' => 6900, 'quantity' => 3]);
        $zeroPrice = $this->product('titan', ['external_id' => '229', 'price' => 5000, 'quantity' => 3]);
        $path = $this->onecFile(['228;;"AKOM";;', '229;;"TITAN";2.000;0.00']);

        $this->artisan('import:onec', ['file' => $path, '--force' => true])->assertSuccessful();

        $this->assertSame(6900.0, (float) $product->fresh()->price);
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertSame(5000.0, (float) $zeroPrice->fresh()->price);
        $this->assertSame(2, $zeroPrice->fresh()->quantity);
    }

    public function test_products_missing_from_file_are_untouched_and_unknown_codes_are_reported_not_created(): void
    {
        $absent = $this->product('absent', ['external_id' => '500', 'price' => 1000, 'quantity' => 4]);
        $notFoundPath = tempnam(sys_get_temp_dir(), 'nf');
        $this->files[] = $notFoundPath;
        $path = $this->onecFile(['999;;"Новый товар";1.000;100.00']);

        $this->artisan('import:onec', ['file' => $path, '--force' => true, '--not-found' => $notFoundPath])
            ->expectsOutputToContain('Нет на сайте')
            ->assertSuccessful();

        $this->assertSame(1000.0, (float) $absent->fresh()->price);
        $this->assertSame(4, $absent->fresh()->quantity);
        $this->assertSame(1, Product::query()->count());
        $this->assertStringContainsString('Новый товар', file_get_contents($notFoundPath));
    }

    public function test_duplicate_code_in_file_uses_last_row(): void
    {
        $product = $this->product('akom', ['external_id' => '228', 'price' => 1000, 'quantity' => 0]);
        $path = $this->onecFile(['228;;"AKOM";1.000;2000.00', '228;;"AKOM";3.000;3000.00']);

        $this->artisan('import:onec', ['file' => $path, '--force' => true])->assertSuccessful();

        $this->assertSame(3000.0, (float) $product->fresh()->price);
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_unknown_header_fails_without_changes(): void
    {
        $product = $this->product('akom', ['external_id' => '228', 'price' => 1000, 'quantity' => 1]);
        $path = $this->file("a;b;c\r\n228;x;y\r\n");

        $this->artisan('import:onec', ['file' => $path, '--force' => true])
            ->expectsOutputToContain('Неизвестный формат')
            ->assertFailed();

        $this->assertSame(1000.0, (float) $product->fresh()->price);
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('import:onec', ['file' => '/no/such/file.csv'])->assertFailed();
    }

    /**
     * @param  list<string>  $lines
     */
    private function onecFile(array $lines): string
    {
        return $this->file("\xEF\xBB\xBF".self::ONEC_HEADER."\r\n".implode("\r\n", $lines)."\r\n");
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'onec');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create($attributes + ['name' => $slug, 'slug' => $slug, 'price' => 1000]);
    }
}
