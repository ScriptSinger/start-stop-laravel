<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyOneCPairsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    public function test_dry_run_does_not_write(): void
    {
        $product = $this->product('akom');

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['РТ-00000001', $product->id]])])
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertNull($product->fresh()->external_id);
    }

    public function test_force_sets_external_id_from_pairs(): void
    {
        $first = $this->product('akom');
        $second = $this->product('titan');

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['РТ-00000001', $first->id], ['1470', $second->id]]), '--force' => true])
            ->expectsOutputToContain('Проставлен external_id: 2')
            ->assertSuccessful();

        $this->assertSame('РТ-00000001', $first->fresh()->external_id);
        $this->assertSame('1470', $second->fresh()->external_id);
    }

    public function test_already_applied_pair_is_not_an_error(): void
    {
        $product = $this->product('akom', ['external_id' => 'РТ-00000001']);

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['РТ-00000001', $product->id]]), '--force' => true])->assertSuccessful();

        $this->assertSame('РТ-00000001', $product->fresh()->external_id);
    }

    public function test_any_error_writes_nothing(): void
    {
        $good = $this->product('akom');
        $deleted = $this->product('titan');
        $deleted->delete();
        $other = $this->product('zubr', ['external_id' => '999']);
        $holder = $this->product('holder', ['external_id' => 'РТ-00000003']);

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([
            ['РТ-00000001', $good->id],
            ['РТ-00000002', $deleted->id],
            ['РТ-00000004', $other->id],
            ['РТ-00000003', $good->id],
            ['РТ-00000005', 9999],
        ]), '--force' => true])
            ->expectsOutputToContain('ничего не записано')
            ->assertFailed();

        $this->assertNull($good->fresh()->external_id);
        $this->assertSame('999', $other->fresh()->external_id);
        $this->assertSame('РТ-00000003', $holder->fresh()->external_id);
    }

    public function test_duplicate_code_or_product_in_file_is_an_error(): void
    {
        $first = $this->product('akom');
        $second = $this->product('titan');

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['1', $first->id], ['1', $second->id], ['2', $first->id]]), '--force' => true])
            ->assertFailed();

        $this->assertNull($first->fresh()->external_id);
        $this->assertNull($second->fresh()->external_id);
    }

    public function test_code_must_exist_in_price_list_when_given(): void
    {
        $product = $this->product('akom');
        $priceList = $this->file("\xEF\xBB\xBFКод;Артикул;Наименование;Свободный остаток;Розничная цена\r\nРТ-00000001;;AKOM;1.000;7000.00\r\n");

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['РТ-00000099', $product->id]]), '--price-list' => $priceList, '--force' => true])
            ->expectsOutputToContain('кода нет в прайсе 1С')
            ->assertFailed();

        $this->artisan('products:apply-onec-pairs', ['file' => $this->pairs([['РТ-00000001', $product->id]]), '--price-list' => $priceList, '--force' => true])
            ->assertSuccessful();

        $this->assertSame('РТ-00000001', $product->fresh()->external_id);
    }

    public function test_malformed_file_fails(): void
    {
        $this->artisan('products:apply-onec-pairs', ['file' => $this->file("Код 1С;id сайта\r\nРТ-1;не число\r\n")])->assertFailed();
        $this->artisan('products:apply-onec-pairs', ['file' => '/no/such/file.csv'])->assertFailed();
    }

    /**
     * @param  list<array{0: string, 1: int}>  $pairs
     */
    private function pairs(array $pairs): string
    {
        return $this->file("\xEF\xBB\xBFКод 1С;id сайта\r\n".implode("\r\n", array_map(fn (array $pair): string => "{$pair[0]};{$pair[1]}", $pairs))."\r\n");
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pairs');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $slug, array $attributes = []): Product
    {
        return Product::query()->create($attributes + ['name' => $slug, 'slug' => $slug, 'price' => 7000]);
    }
}
