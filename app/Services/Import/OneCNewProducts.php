<?php

namespace App\Services\Import;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\BulkAttributeAssigner;
use App\Services\Catalog\TradeInDiscount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Создание на сайте товаров, которые есть в 1С, но которых на сайте нет. Карточки
 * строятся в стиле сайта (OneCProductSpecs) и создаются скрытыми: менеджер
 * просматривает их и включает. Раздел ставится только если он уже есть на сайте;
 * производитель — существующий, а у аккумуляторов новый создаётся.
 */
class OneCNewProducts
{
    /**
     * Название характеристики на сайте по ключу из OneCProductSpecs.
     *
     * @var array<string, string>
     */
    private const ATTRIBUTE_NAMES = [
        'voltage' => 'Напряжение',
        'polarity' => 'Полярность',
        'dimensions' => 'Габариты',
        'cca' => 'Ток холодной прокрутки',
    ];

    /**
     * Бренд в названии 1С → производитель на сайте, если называются по-разному
     * («МЕДВЕДЬ 60Ah» на сайте — производитель «ТЮМЕНЬ»).
     *
     * @var array<string, string>
     */
    private const MANUFACTURER_ALIASES = [
        'медведь' => 'тюмень',
    ];

    public function __construct(
        private readonly OneCProductSpecs $specs,
        private readonly BulkAttributeAssigner $assigner,
        private readonly OneCCategoryAssigner $categoryAssigner,
        private readonly TradeInDiscount $tradeIn,
    ) {}

    /**
     * Что будет создано, без записи в базу.
     *
     * @param  Collection<int, array{external_id: string, name: string, article: string, price: ?float, quantity: ?float}>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function plan(Collection $rows): Collection
    {
        $manufacturers = Manufacturer::query()->pluck('id', 'name')->mapWithKeys(fn (int $id, string $name): array => [$this->normalize($name) => ['id' => $id, 'name' => $name]]);
        $categories = Category::query()->pluck('id', 'name')->mapWithKeys(fn (int $id, string $name): array => [trim($name) => $id]);
        $takenSlugs = [];
        $takenNames = Product::query()->pluck('name')->mapWithKeys(fn (string $name): array => [$this->normalize($name) => true])->all();

        return $rows->map(function (array $row) use ($manufacturers, $categories, &$takenSlugs, &$takenNames): array {
            $spec = $this->specs->build($row, $row['article'] ?? '');
            $spec['name'] = $this->distinctName($spec, $takenNames);
            $takenNames[$this->normalize($spec['name'])] = true;
            $manufacturer = $this->manufacturer($spec, $manufacturers);
            $categoryId = $spec['category'] !== null ? $categories->get($spec['category']) : null;

            if ($spec['category'] !== null && $categoryId === null) {
                $spec['warnings'][] = array_key_exists($spec['category'], OneCProductSpecs::newCategories())
                    ? "раздел «{$spec['category']}» будет создан скрытым"
                    : "раздела «{$spec['category']}» нет на сайте";
            }

            if ($spec['category'] === null) {
                $spec['warnings'][] = 'раздел не определён';
            }

            if ($manufacturer !== null && $manufacturer['id'] === null) {
                $spec['warnings'][] = "будет создан производитель «{$manufacturer['name']}»";
            }

            $slug = $this->uniqueSlug($this->specs->slug($spec['name']), $takenSlugs);
            $takenSlugs[$slug] = true;

            return [...$spec, 'slug' => $slug, 'manufacturer' => $manufacturer, 'category_id' => $categoryId];
        })->values();
    }

    /**
     * Создать товары по плану одной транзакцией.
     *
     * @param  Collection<int, array<string, mixed>>  $plan
     */
    public function create(Collection $plan): int
    {
        return DB::transaction(function () use ($plan): int {
            $createdManufacturers = [];

            foreach ($plan as $item) {
                $manufacturerId = $item['manufacturer']['id'] ?? null;

                if ($item['manufacturer'] !== null && $manufacturerId === null) {
                    $key = $this->normalize($item['manufacturer']['name']);
                    $createdManufacturers[$key] ??= Manufacturer::query()->create([
                        'name' => $item['manufacturer']['name'],
                        'slug' => $this->uniqueManufacturerSlug($item['manufacturer']['name']),
                    ])->id;
                    $manufacturerId = $createdManufacturers[$key];
                }

                $product = Product::query()->create([
                    'manufacturer_id' => $manufacturerId,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'sku' => $item['sku'],
                    'external_id' => $item['external_id'],
                    'code' => $item['code'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'trade_in_discount' => $this->tradeInDiscount($item),
                    'status' => false,
                ]);

                $categoryId = $item['category_id'] ?? ($item['category'] !== null ? $this->categoryAssigner->findOrCreate($item['category'])?->id : null);

                if ($categoryId !== null) {
                    $product->categories()->attach($categoryId);
                }

                $this->assignAttributes($product, $item['attributes']);
            }

            return $plan->count();
        });
    }

    /**
     * Скидка за обмен у легковых и грузовых аккумуляторов по ёмкости; у мото и
     * остальных товаров её нет.
     *
     * @param  array<string, mixed>  $item
     */
    private function tradeInDiscount(array $item): ?int
    {
        if (! in_array($item['category'], ['Аккумуляторы', 'Грузовые аккумуляторы'], true) || ! isset($item['attributes']['capacity'])) {
            return null;
        }

        return $this->tradeIn->forCapacity((float) str_replace(',', '.', $item['attributes']['capacity']));
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function assignAttributes(Product $product, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $attributeValue = $key === 'capacity'
                ? $this->value((int) config('shop.battery_fitment.capacity_exact_attribute'), $value)
                : $this->value($this->attributeId(self::ATTRIBUTE_NAMES[$key]), $value, $key);

            if ($attributeValue !== null) {
                $this->assigner->assign([$product->id], $attributeValue, true);
            }
        }
    }

    private function attributeId(string $name): ?int
    {
        return once(fn () => Attribute::query()->pluck('id', 'name'))->get($name);
    }

    /**
     * Существующее значение характеристики или новое: «600А» и «600A», размеры
     * «242x175x190» и «Евро L2 (242 x 175 x 190 мм)» считаются одним значением.
     */
    private function value(?int $attributeId, string $value, string $kind = ''): ?AttributeValue
    {
        if ($attributeId === null || ! Attribute::query()->whereKey($attributeId)->exists()) {
            return null;
        }

        $wanted = $this->valueKey($value, $kind);
        $existing = AttributeValue::query()->where('attribute_id', $attributeId)->get()
            ->first(fn (AttributeValue $candidate): bool => $this->valueKey($candidate->value, $kind) === $wanted);

        return $existing ?? AttributeValue::query()->create([
            'attribute_id' => $attributeId,
            'value' => $value,
            'sort_order' => 0,
        ]);
    }

    private function valueKey(string $value, string $kind): string
    {
        if ($kind === 'dimensions' && preg_match('/(\d{2,3})\D+(\d{2,3})\D+(\d{2,3})/u', $value, $dims) === 1) {
            $numbers = [$dims[1], $dims[2], $dims[3]];
            sort($numbers);

            return implode('x', $numbers);
        }

        return mb_strtolower((string) preg_replace('/\s+/u', '', strtr($value, ['А' => 'A', 'а' => 'a', 'х' => 'x', 'Х' => 'X'])));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  Collection<string, array{id: int, name: string}>  $manufacturers
     * @return array{id: int|null, name: string}|null
     */
    private function manufacturer(array $spec, Collection $manufacturers): ?array
    {
        $words = preg_split('/\s+/u', (string) ($spec['is_battery'] ? $spec['brand'] : $spec['source_name']), -1, PREG_SPLIT_NO_EMPTY);

        if ($spec['is_battery']) {
            // «FireBall» и «FIRE BALL» — один бренд: сравниваем без пробелов.
            $compact = $manufacturers->mapWithKeys(fn (array $manufacturer, string $key): array => [str_replace(' ', '', $key) => $manufacturer]);

            for ($length = min(3, count($words)); $length >= 1; $length--) {
                $key = str_replace(' ', '', $this->normalize(implode(' ', array_slice($words, 0, $length))));
                $found = $compact->get($key) ?? $compact->get(self::MANUFACTURER_ALIASES[$key] ?? '');

                if ($found !== null) {
                    return $found;
                }
            }

            return $words === [] ? null : ['id' => null, 'name' => $words[0]];
        }

        $text = ' '.$this->normalize($spec['source_name']).' ';

        return $manufacturers
            ->filter(fn (array $manufacturer, string $key): bool => mb_strlen($key) >= 3 && str_contains($text, " {$key} "))
            ->sortByDesc(fn (array $manufacturer, string $key): int => mb_strlen($key))
            ->first();
    }

    /**
     * Название, которого ещё нет на сайте. У вариантов одной модели аккумулятора
     * (разный ток или размер) названия совпали бы, поэтому добавляется отличие:
     * ток («610A»), затем типоразмер («L2L»), затем габариты.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, true>  $taken
     */
    private function distinctName(array $spec, array $taken): string
    {
        if (! isset($taken[$this->normalize($spec['name'])]) || ! $spec['is_battery']) {
            return $spec['name'];
        }

        foreach ([$spec['attributes']['cca'] ?? null, $spec['code'], $spec['attributes']['dimensions'] ?? null] as $difference) {
            if ($difference !== null && ! str_contains($spec['name'], $difference) && ! isset($taken[$this->normalize("{$spec['name']} {$difference}")])) {
                return "{$spec['name']} {$difference}";
            }
        }

        return $spec['name'];
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(str_replace(['ё', 'Ё'], 'е', $value))));
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function uniqueSlug(string $base, array $taken): string
    {
        $base = $base !== '' ? $base : (string) Str::uuid();
        $slug = $base;
        $suffix = 2;

        while (isset($taken[$slug]) || Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueManufacturerSlug(string $name): string
    {
        $base = Str::slug($name) ?: (string) Str::uuid();
        $slug = $base;
        $suffix = 2;

        while (Manufacturer::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
