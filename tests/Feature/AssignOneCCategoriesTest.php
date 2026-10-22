<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Import\OneCProductSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignOneCCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "\xEF\xBB\xBFКод;Артикул;Наименование;Свободный остаток;Розничная цена\r\n";

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'воздушный фильтр' => ['Ф. воздушный Sufix SO-1020 (аналог SB678)', 'Фильтры воздушные'],
            'салонный угольный' => ['Ф. салонный угольный AMD.FC753С (аналог SAK338)', 'Фильтры салонные'],
            'топливный' => ['Ф. топливный SUFIX SQ1095', 'Фильтры топливные'],
            'масляный' => ['Ф. масл. Madfil MO-0001 (аналог SM142)', 'Фильтры масляные'],
            'колодки' => ['Колодки тормозные дисковые CTR GK1231', 'Тормозные колодки'],
            'запчасть' => ['Опора шаровая нижняя перед лев Febest 2320GVFL', 'Прочие запчасти'],
            'автокресло' => ['Автокресло COSTA KBH305 ISOFIT', 'Автокресла'],
            'канистра' => ['Канистра металл. 20л AVS BJM-20 зеленая', 'Канистры'],
            'краска' => ['Краска-спрей (эмаль) RAL 9005 черная глянцевая 450мл', 'Краски и эмали'],
            'щётка для чистки' => ['Щетка для очистки кожи AVS AD-1298', 'Щетки для чистки'],
            'чернитель' => ['Чернитель (блеск) для шин 255мл триггер LAVR', 'Полироли'],
            'ГУР' => ['Жидкость для ГУРа (масло) 1л PSF', 'Технические жидкости'],
            'крепление АКБ' => ['Болт крепления АКБ М8 HYUNDAI/KIA', 'Крепление АКБ'],
            'инструмент Detool' => ['Detool трещетка реверсивная 1/4 72 зубца DTL0103', 'Инструменты'],
        ];
    }

    #[DataProvider('names')]
    public function test_category_is_chosen_by_name(string $name, string $category): void
    {
        $this->assertSame($category, (new OneCProductSpecs)->category($name));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $product = $this->product('1590', 'Ф. воздушный Sufix SO-1020');

        $this->artisan('products:assign-onec-categories', ['price-list' => $this->priceList()])
            ->expectsOutputToContain('Пробный прогон')
            ->assertSuccessful();

        $this->assertSame(0, $product->categories()->count());
        $this->assertSame(0, Category::query()->count());
    }

    public function test_force_attaches_existing_category_and_creates_missing_ones_hidden_with_parent(): void
    {
        Category::query()->create(['name' => 'Автотовары', 'slug' => 'avtotovary', 'status' => true]);
        Category::query()->create(['name' => 'Полироли', 'slug' => 'polirol', 'status' => true]);
        $filter = $this->product('1590', 'Ф. воздушный Sufix SO-1020');
        $polish = $this->product('1289', 'Чернитель (блеск) для шин 255мл');
        $seat = $this->product('1490', 'Автокресло COSTA KBH305');

        $this->artisan('products:assign-onec-categories', ['price-list' => $this->priceList(), '--force' => true])->assertSuccessful();

        $air = Category::query()->where('name', 'Фильтры воздушные')->firstOrFail();
        $this->assertFalse($air->status);
        $this->assertSame('Автозапчасти', $air->parent->name);
        $this->assertFalse($air->parent->status);
        $this->assertNull($air->parent->parent_id);
        $this->assertSame('filtry-vozdushnye', $air->slug);
        $this->assertSame([$air->id], $filter->categories->pluck('id')->all());

        $this->assertSame(['Полироли'], $polish->categories->pluck('name')->all());
        $this->assertSame(1, Category::query()->where('name', 'Полироли')->count());

        $seatCategory = Category::query()->where('name', 'Автокресла')->firstOrFail();
        $this->assertSame('Автотовары', $seatCategory->parent->name);
        $this->assertTrue($seatCategory->parent->status);
        $this->assertSame([$seatCategory->id], $seat->categories->pluck('id')->all());
    }

    public function test_products_that_already_have_a_category_are_not_touched(): void
    {
        $existing = Category::query()->create(['name' => 'Прочее', 'slug' => 'prochee', 'status' => true]);
        $product = $this->product('1590', 'Ф. воздушный Sufix SO-1020');
        $product->categories()->attach($existing);

        $this->artisan('products:assign-onec-categories', ['price-list' => $this->priceList(), '--force' => true])->assertSuccessful();

        $this->assertSame(['Прочее'], $product->fresh()->categories->pluck('name')->all());
        $this->assertSame(0, Category::query()->where('name', 'Фильтры воздушные')->count());
    }

    public function test_unknown_goods_stay_without_category(): void
    {
        $product = $this->product('777', 'Что-то совсем непонятное');

        $this->artisan('products:assign-onec-categories', ['price-list' => $this->priceList(), '--force' => true])->assertSuccessful();

        $this->assertSame(0, $product->categories()->count());
    }

    private function priceList(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'onec');
        file_put_contents($path, self::HEADER
            .'1590;;"Ф. воздушный Sufix SO-1020 (аналог SB678)";;100.00'."\r\n"
            .'1289;;"Чернитель (блеск) для шин 255мл триггер LAVR";;350.00'."\r\n"
            .'1490;;"Автокресло COSTA KBH305 ISOFIT";;9000.00'."\r\n"
            .'777;;"Что-то совсем непонятное";;1.00'."\r\n");
        $this->files[] = $path;

        return $path;
    }

    private function product(string $externalId, string $name): Product
    {
        return Product::query()->create(['name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 1, 'external_id' => $externalId]);
    }
}
