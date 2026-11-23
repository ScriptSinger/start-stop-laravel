<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleXMLElement;
use Tests\TestCase;

class GenerateFeedTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private Category $category;

    private Manufacturer $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/yml.xml');
        @mkdir(dirname($this->path), 0777, true);

        config(['shop.feed' => ['category_ids' => [], 'category_slugs' => ['akkumulyatori', 'zaryadnye-ustroystva']]]);

        $parent = Category::query()->create(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'status' => true]);
        $this->category = Category::query()->create(['name' => 'Легковые', 'slug' => 'legkovye', 'parent_id' => $parent->id, 'status' => true]);
        $this->brand = Manufacturer::query()->create(['name' => 'Tyumen', 'slug' => 'tyumen']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(dirname($this->path).'/yml.xml*'));

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $slug, array $attributes = []): Product
    {
        $product = Product::query()->create([
            'name' => "АКБ {$slug}", 'slug' => $slug, 'price' => 7000, 'quantity' => 3, 'status' => true,
            'manufacturer_id' => $this->brand->id, 'image' => 'catalog/products/akb.jpg',
            ...$attributes,
        ]);
        $product->categories()->attach($this->category);

        return $product;
    }

    private function feed(): SimpleXMLElement
    {
        $this->artisan('feed:generate', ['--path' => $this->path])->assertSuccessful();

        $xml = simplexml_load_file($this->path);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        return $xml;
    }

    public function test_creates_valid_yml_with_shop_and_categories(): void
    {
        $this->product('akb-1');

        $xml = $this->feed();

        $this->assertSame('yml_catalog', $xml->getName());
        $this->assertNotEmpty((string) $xml['date']);
        $this->assertSame('RUB', (string) $xml->shop->currencies->currency['id']);
        $this->assertCount(2, $xml->shop->categories->category);
        $this->assertSame((string) $this->category->parent_id, (string) $xml->shop->categories->category[1]['parentId']);
        $this->assertFileDoesNotExist($this->path.'.tmp');
        $this->assertSame([], glob($this->path.'.*.tmp'));
    }

    public function test_contains_only_enabled_products_in_stock_or_on_order(): void
    {
        $this->product('in-stock');
        $this->product('on-order', ['quantity' => 0, 'supplier_quantity' => 5, 'supplier_price' => 6500]);
        $this->product('disabled', ['status' => false]);
        $this->product('absent', ['quantity' => 0, 'supplier_quantity' => 0]);

        $offers = $this->feed()->shop->offers->offer;

        $this->assertCount(2, $offers);
        $byUrl = [];
        foreach ($offers as $offer) {
            $byUrl[(string) $offer->url] = $offer;
        }

        $this->assertSame('true', (string) $byUrl[route('product.show', 'in-stock')]['available']);
        $this->assertSame('false', (string) $byUrl[route('product.show', 'on-order')]['available']);
        $this->assertSame('6500', (string) $byUrl[route('product.show', 'on-order')]->price);
    }

    public function test_offer_has_required_fields_and_params(): void
    {
        $product = $this->product('akb-1', ['sku' => 'T-60', 'description' => '<p>Мощный <b>АКБ</b></p><script>alert(1)</script>']);
        $capacity = Attribute::query()->create(['name' => 'Ёмкость']);
        $hidden = Attribute::query()->create(['name' => 'Служебная', 'show_on_card' => false]);
        $product->attributeValues()->attach([
            $capacity->values()->create(['value' => '60 Ah'])->id,
            $hidden->values()->create(['value' => 'скрыто'])->id,
        ]);

        $offer = $this->feed()->shop->offers->offer[0];

        $this->assertSame((string) $product->id, (string) $offer['id']);
        $this->assertSame(route('product.show', $product), (string) $offer->url);
        $this->assertSame('7000', (string) $offer->price);
        $this->assertSame('RUB', (string) $offer->currencyId);
        $this->assertSame((string) $this->category->id, (string) $offer->categoryId);
        $this->assertStringStartsWith('http', (string) $offer->picture);
        $this->assertStringEndsWith('akb.jpg', (string) $offer->picture);
        $this->assertSame('АКБ akb-1', (string) $offer->name);
        $this->assertSame('Tyumen', (string) $offer->vendor);
        $this->assertSame('T-60', (string) $offer->vendorCode);
        $this->assertStringNotContainsString('<', (string) $offer->description);
        $this->assertCount(1, $offer->param);
        $this->assertSame('Ёмкость', (string) $offer->param[0]['name']);
        $this->assertSame('60 Ah', (string) $offer->param[0]);
        $this->assertCount(0, $offer->oldprice);
    }

    public function test_special_characters_are_escaped(): void
    {
        $this->product('akb-1', ['name' => 'АКБ "Тест" <b> & Ко', 'description' => "Описание \x01 с & и <i>тегом</i>"]);

        $this->feed();
        $raw = (string) file_get_contents($this->path);

        $this->assertStringContainsString('АКБ &quot;Тест&quot; &lt;b&gt; &amp; Ко', $raw);
        $this->assertStringNotContainsString("\x01", $raw);
        $this->assertSame('АКБ "Тест" <b> & Ко', (string) simplexml_load_string($raw)->shop->offers->offer[0]->name);
    }

    public function test_products_without_price_are_skipped(): void
    {
        $this->product('priced');
        $this->product('free', ['price' => 0]);
        $this->product('on-order-no-price', ['quantity' => 0, 'supplier_quantity' => 5, 'supplier_price' => 0]);

        $offers = $this->feed()->shop->offers->offer;

        $this->assertCount(1, $offers);
        $this->assertSame(route('product.show', 'priced'), (string) $offers[0]->url);
    }

    public function test_only_allowed_categories_and_their_subcategories_are_exported(): void
    {
        $chargers = Category::query()->create(['name' => 'Зарядные устройства', 'slug' => 'zaryadnye-ustroystva', 'status' => true]);
        $oils = Category::query()->create(['name' => 'Моторное масло', 'slug' => 'motornoe-maslo', 'status' => true]);
        $wipers = Category::query()->create(['name' => 'Стеклоочистители', 'slug' => 'stekloochistiteli', 'status' => true]);
        $wiperBrand = Category::query()->create(['name' => 'Bosch', 'slug' => 'bosch-wipers', 'parent_id' => $wipers->id, 'status' => true]);

        $this->product('battery');
        $this->product('charger')->categories()->sync([$chargers->id]);
        $this->product('oil')->categories()->sync([$oils->id]);
        $this->product('wiper')->categories()->sync([$wiperBrand->id]);
        $this->product('mixed')->categories()->sync([$oils->id, $this->category->id]);

        $xml = $this->feed()->shop;
        $urls = array_map(fn (SimpleXMLElement $offer): string => (string) $offer->url, iterator_to_array($xml->offers->offer, false));

        $this->assertEqualsCanonicalizing([
            route('product.show', 'battery'),
            route('product.show', 'charger'),
            route('product.show', 'mixed'),
        ], $urls);

        $mixed = collect(iterator_to_array($xml->offers->offer, false))->first(fn ($offer) => str_ends_with((string) $offer->url, '/mixed'));
        $this->assertSame((string) $this->category->id, (string) $mixed->categoryId);

        $names = array_map('strval', iterator_to_array($xml->categories->category, false));
        $this->assertEqualsCanonicalizing(['Аккумуляторы', 'Легковые', 'Зарядные устройства'], $names);
    }

    public function test_disabled_allowed_category_products_are_skipped(): void
    {
        $chargers = Category::query()->create(['name' => 'Зарядные устройства', 'slug' => 'zaryadnye-ustroystva', 'status' => false]);
        $this->product('charger')->categories()->sync([$chargers->id]);

        $this->assertCount(0, $this->feed()->shop->offers->offer);
    }

    public function test_special_price_is_the_offer_price(): void
    {
        $this->product('akb-1', ['special_price' => 6500]);

        $this->assertSame('6500', (string) $this->feed()->shop->offers->offer[0]->price);
    }

    public function test_rebuilt_every_night(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'feed:generate'));

        $this->assertNotNull($event);
        $this->assertSame('45 3 * * *', $event->expression);
        $this->assertSame('Asia/Yekaterinburg', $event->timezone);
    }
}
