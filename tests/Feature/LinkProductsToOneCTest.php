<?php

namespace Tests\Feature;

use App\Enums\OneCLinkStatus;
use App\Models\Product;
use App\Services\Import\OneCProductLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkProductsToOneCTest extends TestCase
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

    public function test_links_by_same_code_by_name_and_reports_the_rest(): void
    {
        $sameCode = $this->product('same', '228');
        $byName = $this->product('by-name', '1252');
        $ambiguous = $this->product('ambiguous', '52');
        $notInOneC = $this->product('not-in-onec', '62');
        $unknown = $this->product('unknown', '377');
        $mismatch = $this->product('mismatch', '244');

        $results = (new OneCProductLinker)->link(
            Product::query()->orderBy('id')->get(),
            $this->rows([
                ['228', 'Аккумулятор AKOM 60Ah о.п.'],
                ['РТ-00000805', 'Detool набор отверток DTL0071'],
                ['РТ-00000001', 'Аккумулятор FIRE BALL 60Ah о.п. 510A'],
                ['РТ-00000002', 'Аккумулятор FIRE BALL 60Ah о.п. 510A'],
                ['244', 'Аккумулятор XTREME Silver 60Ah п.п. 540A L2R 242x175x190'],
            ]),
            $this->rows([
                ['228', 'Аккумулятор AKOM 60Ah о.п.'],
                ['1252', 'Detool  набор отверток DTL0071'],
                ['52', 'Аккумулятор FIRE BALL 60Ah о.п. 510A'],
                ['62', 'VARTA Blue Dynamic 60 Ah Asia О.П.'],
                ['244', 'Аккумулятор HUSKY 50Ah о.п. 450А L1L 207x175x190'],
            ]),
        )->keyBy(fn (array $result): int => $result['product']->id);

        $this->assertSame(OneCLinkStatus::SameCode, $results[$sameCode->id]['status']);
        $this->assertSame('228', $results[$sameCode->id]['external_id']);
        $this->assertSame(OneCLinkStatus::ByName, $results[$byName->id]['status']);
        $this->assertSame('РТ-00000805', $results[$byName->id]['external_id']);
        $this->assertSame(OneCLinkStatus::Ambiguous, $results[$ambiguous->id]['status']);
        $this->assertNull($results[$ambiguous->id]['external_id']);
        $this->assertSame(OneCLinkStatus::NotInOneC, $results[$notInOneC->id]['status']);
        $this->assertSame(OneCLinkStatus::Unknown, $results[$unknown->id]['status']);
        $this->assertSame(OneCLinkStatus::NameMismatch, $results[$mismatch->id]['status']);
        $this->assertNull($results[$mismatch->id]['external_id']);
    }

    public function test_dry_run_does_not_change_database(): void
    {
        $product = $this->product('detool', '1252', '1252');

        $this->artisan('products:link-onec', $this->arguments())
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame('1252', $product->fresh()->external_id);
    }

    public function test_force_sets_onec_code_and_keeps_unlinked_products_as_is(): void
    {
        $byName = $this->product('detool', '1252', '1252');
        $unlinked = $this->product('unlinked', '62', '62');
        $noSku = $this->product('no-sku', null, '777');

        $this->artisan('products:link-onec', $this->arguments() + ['--force' => true])->assertSuccessful();

        $this->assertSame('РТ-00000805', $byName->fresh()->external_id);
        $this->assertSame('62', $unlinked->fresh()->external_id);
        $this->assertSame('777', $noSku->fresh()->external_id);
        $this->assertSame('1252', $byName->fresh()->sku);
    }

    public function test_clear_unlinked_empties_external_id_of_unlinked_products_only(): void
    {
        $byName = $this->product('detool', '1252', '1252');
        $unlinked = $this->product('unlinked', '62', '62');
        $noSku = $this->product('no-sku', null, '777');

        $this->artisan('products:link-onec', $this->arguments() + ['--force' => true, '--clear-unlinked' => true])->assertSuccessful();

        $this->assertSame('РТ-00000805', $byName->fresh()->external_id);
        $this->assertNull($unlinked->fresh()->external_id);
        $this->assertSame('777', $noSku->fresh()->external_id);
    }

    public function test_duplicate_final_external_id_writes_nothing(): void
    {
        $byName = $this->product('detool', '1252', '1252');
        $holder = $this->product('holder', '500', 'РТ-00000805');

        $this->artisan('products:link-onec', $this->arguments() + ['--force' => true])
            ->expectsOutputToContain('повторяются')
            ->assertFailed();

        $this->assertSame('1252', $byName->fresh()->external_id);
        $this->assertSame('РТ-00000805', $holder->fresh()->external_id);
    }

    public function test_writes_report_for_every_product(): void
    {
        $this->product('detool', '1252');
        $report = tempnam(sys_get_temp_dir(), 'report');
        $this->files[] = $report;

        $this->artisan('products:link-onec', $this->arguments() + ['--report' => $report])->assertSuccessful();

        $this->assertStringContainsString('РТ-00000805', file_get_contents($report));
    }

    public function test_swapped_files_fail_with_format_error(): void
    {
        [$onec, $tradeHouse] = $this->files();

        $this->artisan('products:link-onec', ['onec' => $tradeHouse, 'trade-house' => $onec])
            ->expectsOutputToContain('ожидался формат')
            ->assertFailed();
    }

    /**
     * @return array{onec: string, trade-house: string}
     */
    private function arguments(): array
    {
        [$onec, $tradeHouse] = $this->files();

        return ['onec' => $onec, 'trade-house' => $tradeHouse];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function files(): array
    {
        $onec = tempnam(sys_get_temp_dir(), 'onec');
        file_put_contents($onec, "\xEF\xBB\xBF".self::ONEC_HEADER."\r\n".'РТ-00000805;;"Detool набор отверток DTL0071";1.000;1020.00'."\r\n");

        $tradeHouse = tempnam(sys_get_temp_dir(), 'td');
        file_put_contents($tradeHouse, mb_convert_encoding(self::TRADE_HOUSE_HEADER."\r\n".'1252;"Инструмент";"";"";"Detool набор отверток DTL0071";"Detool";1020,00;1,0000;"шт";"";"1"'."\r\n", 'Windows-1251', 'UTF-8'));

        $this->files = [...$this->files, $onec, $tradeHouse];

        return [$onec, $tradeHouse];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<array{external_id: string, name: string, price: ?float, quantity: ?float}>
     */
    private function rows(array $pairs): array
    {
        return array_map(fn (array $pair): array => ['external_id' => $pair[0], 'name' => $pair[1], 'price' => null, 'quantity' => null], $pairs);
    }

    private function product(string $slug, ?string $sku, ?string $externalId = null): Product
    {
        return Product::query()->create(['name' => $slug, 'slug' => $slug, 'price' => 1000, 'sku' => $sku, 'external_id' => $externalId]);
    }
}
