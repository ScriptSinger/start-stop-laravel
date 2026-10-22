<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\TradeInDiscount;
use App\Services\Import\OneCProductSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BatteryTradeInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{float, int}>
     */
    public static function tiers(): array
    {
        return [
            '35 Ач' => [35, 400],
            '45 Ач' => [45, 700],
            '60 Ач' => [60, 1000],
            '68 Ач' => [68, 1000],
            '70 Ач' => [70, 1200],
            '80 Ач' => [80, 1300],
            '100 Ач' => [100, 1500],
            '140 Ач' => [140, 2000],
            '190 Ач' => [190, 2500],
            '225 Ач' => [225, 3000],
        ];
    }

    #[DataProvider('tiers')]
    public function test_discount_follows_capacity_tiers(float $capacity, int $discount): void
    {
        $this->assertSame($discount, (new TradeInDiscount)->forCapacity($capacity));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $battery = $this->battery('TEST 60Ah О.П.', 60);

        $this->artisan('products:fill-trade-in')->expectsOutputToContain('Пробный прогон')->assertSuccessful();

        $this->assertNull($battery->fresh()->trade_in_discount);
    }

    public function test_force_sets_discount_only_for_hidden_batteries_without_it(): void
    {
        $hidden = $this->battery('TEST 60Ah О.П.', 60);
        $big = $this->battery('TEST 190Ah П.П.', 190);
        $withDiscount = $this->battery('TEST 75Ah О.П.', 75, ['trade_in_discount' => 500]);
        $active = $this->battery('TEST 62Ah О.П.', 62, ['status' => true]);
        $noCapacity = $this->battery('TEST без ёмкости', null);
        $other = Product::query()->create(['name' => 'Лампа', 'slug' => 'lampa', 'price' => 1, 'status' => false]);

        $this->artisan('products:fill-trade-in', ['--force' => true])->assertSuccessful();

        $this->assertSame(1000.0, (float) $hidden->fresh()->trade_in_discount);
        $this->assertSame(2500.0, (float) $big->fresh()->trade_in_discount);
        $this->assertSame(500.0, (float) $withDiscount->fresh()->trade_in_discount);
        $this->assertNull($active->fresh()->trade_in_discount);
        $this->assertNull($noCapacity->fresh()->trade_in_discount);
        $this->assertNull($other->fresh()->trade_in_discount);
    }

    public function test_lookalike_letters_in_names_are_fixed(): void
    {
        $specs = new OneCProductSpecs;

        $this->assertSame('Уплотнитель стекла 681640K010 TOYOTA', $specs->fixLookalikes('Уплoтнитeль cтeклa 681640K010 TOYOTA'));
        $this->assertSame('Ф. воздушный Lynx LA-1505', $specs->fixLookalikes('Ф. воздушный Lynx LА-1505'));
        $this->assertSame('Домкрат ECO', $specs->fixLookalikes('Домкрат EСO'));
        $this->assertSame('арт.CA-030-19S', $specs->fixLookalikes('арт.СA-030-19S'));
        $this->assertSame('Антифриз FELIX Carbox G12+', $specs->fixLookalikes('Антифриз FELIX Carbox G12+'));
        $this->assertSame('Щетка ALCA', $specs->fixLookalikes('Щетка ALCA'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function battery(string $name, ?float $capacity, array $attributes = []): Product
    {
        static $category = null;
        $this->seedBase();

        $product = Product::query()->create($attributes + ['name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 7000, 'status' => false]);
        $product->categories()->attach(Category::query()->where('name', 'Аккумуляторы')->value('id'));

        if ($capacity !== null) {
            $value = Attribute::query()->findOrFail(15)->values()->firstOrCreate(['value' => "{$capacity} Ah"], ['sort_order' => 0]);
            $product->attributeValues()->attach($value->id);
        }

        return $product;
    }

    private function seedBase(): void
    {
        config(['shop.battery_fitment.capacity_exact_attribute' => 15, 'shop.battery_fitment.category_ids' => [1]]);

        if (Category::query()->count() === 0) {
            $category = new Category(['name' => 'Аккумуляторы', 'slug' => 'akkumulyatori', 'status' => true]);
            $category->id = 1;
            $category->save();

            $attribute = new Attribute(['name' => 'Емкость', 'sort_order' => 0]);
            $attribute->id = 15;
            $attribute->save();
        }
    }
}
