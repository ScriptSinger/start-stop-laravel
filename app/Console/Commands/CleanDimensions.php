<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\Catalog\TradeInTypeMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('trade-in:clean-dimensions {--force : Записать; без флага — только отчёт}')]
#[Description('Оставить в «Габаритах» только размеры: тип корпуса и ёмкость из названий значений убрать, предварительно проставив товарам тип корпуса')]
class CleanDimensions extends Command
{
    /**
     * «Габариты» — это размеры. Старые значения смешивали их с другими данными:
     *  - «Азия B19 (187 x 127 x 227 мм)» — тип корпуса и размеры в одном поле;
     *  - «Груз A (130 - 140 Ah)» — класс грузового АКБ с ёмкостью вместо размеров.
     * Тип теперь хранит поле «Тип корпуса». Поэтому сначала каждому товару ставится тип
     * (по коду в названии, если не задан), и только потом значение очищается: у обычных
     * типов остаются размеры («187 x 127 x 227», миллиметры подразумеваются), у грузовых значение снимается с
     * товара и удаляется (размеров у него нет). Если тип товару поставить не удалось, его
     * значение не трогается. Перед запуском: php artisan db:backup.
     */
    public function handle(TradeInTypeMatcher $matcher): int
    {
        $dimensionsAttribute = (int) config('shop.battery_fitment.attributes.dimensions');

        $values = AttributeValue::query()
            ->where('attribute_id', $dimensionsAttribute)
            ->with(['products' => fn ($products) => $products->with('attributeValues')])
            ->get();

        $capacityValues = $values->filter(fn (AttributeValue $value): bool => preg_match('/^Груз\s+\S+.*Ah/iu', $value->value) === 1);
        $typeValues = $values->filter(fn (AttributeValue $value): bool => self::splitTypeAndSize($value->value) !== null);

        // Размеры без типа приводятся к единому виду «187 x 127 x 227»: «187 x 127 x 227 мм»,
        // «277x175x190», а также размеры, смешанные с посторонним текстом («65-750 (302x182x192)»).
        $sizeValues = $values
            ->reject(fn (AttributeValue $value): bool => $typeValues->contains('id', $value->id) || $capacityValues->contains('id', $value->id))
            ->filter(fn (AttributeValue $value): bool => TradeInTypeMatcher::parseDimensions($value->value) !== null
                && TradeInTypeMatcher::formatDimensions($value->value) !== $value->value);
        $withExtraText = $sizeValues->filter(fn (AttributeValue $value): bool => preg_match('/^\s*\d{2,3}\s*[xх×]\s*\d{2,3}\s*[xх×]\s*\d{2,3}\s*(?:мм)?\s*$/iu', $value->value) !== 1);

        $renames = $typeValues->mapWithKeys(fn (AttributeValue $value): array => [$value->id => self::splitTypeAndSize($value->value)[1]])->toBase()
            ->union($sizeValues->mapWithKeys(fn (AttributeValue $value): array => [$value->id => TradeInTypeMatcher::formatDimensions($value->value)])->toBase());
        $conflicts = $renames->filter(fn (string $size, int $id): bool => $values->contains(fn (AttributeValue $other): bool => $other->id !== $id && $other->value === $size)
            || $renames->except($id)->contains($size));

        $untyped = fn (Collection $group): Collection => $group->flatMap->products->unique('id')
            ->filter(fn (Product $product): bool => $product->trade_in_type_id === null);
        $unmatched = $untyped($capacityValues->merge($typeValues))->filter(fn (Product $product): bool => $matcher->typeForProduct($product) === null);

        $this->components->twoColumnDetail('Грузовых значений с ёмкостью (будут сняты с товаров и удалены)', (string) $capacityValues->count());
        $renameAttribute = Attribute::query()->whereKey($dimensionsAttribute)->where('name', 'Габариты')->exists();

        $this->components->twoColumnDetail('Название характеристики «Габариты» → «Габариты (мм)»', $renameAttribute ? 'будет изменено' : 'уже приведено');
        $this->components->twoColumnDetail('Значений «тип + размеры» (останутся только размеры)', (string) $typeValues->count());
        $this->components->twoColumnDetail('Размеры без типа, у которых убирается «мм» и пробелы приводятся к виду «187 x 127 x 227»', (string) $sizeValues->count());
        $this->components->twoColumnDetail('…значений с таким же размером, как у другого (будут слиты с ним)', (string) $conflicts->count());
        $this->components->twoColumnDetail('Товаров без типа, которым он будет проставлен', (string) ($untyped($capacityValues->merge($typeValues))->count() - $unmatched->count()));
        $this->components->twoColumnDetail('Товаров, у которых тип определить не удалось (значение не трогается)', (string) $unmatched->count());

        foreach ($withExtraText as $value) {
            $this->line("  Размеры вперемешку с текстом: «{$value->value}» → «".TradeInTypeMatcher::formatDimensions($value->value)."» (посторонний текст отбрасывается, товаров: {$value->products->count()})");
        }

        foreach ($typeValues as $value) {
            $this->line("  «{$value->value}» → «{$renames[$value->id]}» (товаров: {$value->products->count()})");
        }

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: ничего не записано. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        $skip = $unmatched->pluck('id')->all();

        DB::transaction(function () use ($capacityValues, $typeValues, $sizeValues, $renames, $matcher, $skip, $dimensionsAttribute, $renameAttribute): void {
            foreach ($capacityValues->merge($typeValues) as $value) {
                foreach ($value->products as $product) {
                    if ($product->trade_in_type_id === null && ($type = $matcher->typeForProduct($product)) !== null) {
                        $product->update(['trade_in_type_id' => $type->id]);
                    }
                }
            }

            foreach ($capacityValues as $value) {
                $value->products->reject(fn (Product $product): bool => in_array($product->id, $skip, true))
                    ->each(fn (Product $product): int => $product->attributeValues()->detach($value->id));

                if (! $value->products()->exists()) {
                    $value->delete();
                }
            }

            foreach ($typeValues as $value) {
                if ($value->products->pluck('id')->intersect($skip)->isEmpty()) {
                    $this->renameOrMerge($value, $renames[$value->id], $dimensionsAttribute);
                }
            }

            foreach ($sizeValues as $value) {
                $this->renameOrMerge($value, $renames[$value->id], $dimensionsAttribute);
            }

            if ($renameAttribute) {
                Attribute::query()->whereKey($dimensionsAttribute)->update(['name' => 'Габариты (мм)']);
            }
        });

        $this->info('Готово: типы проставлены, в «Габаритах» остались размеры в виде «187 x 127 x 227».');

        return self::SUCCESS;
    }

    /**
     * Новое название значения; если значение с таким названием уже есть (одинаковый размер
     * в двух написаниях), товары переносятся на него, а дубль удаляется.
     */
    private function renameOrMerge(AttributeValue $value, string $name, int $attributeId): void
    {
        $existing = AttributeValue::query()
            ->where('attribute_id', $attributeId)
            ->where('value', $name)
            ->whereKeyNot($value->id)
            ->first();

        if ($existing === null) {
            $value->update(['value' => $name]);

            return;
        }

        $value->products->each(fn (Product $product): array => $product->attributeValues()->syncWithoutDetaching([$existing->id]));
        $value->products()->detach();
        $value->delete();
    }

    /**
     * «Азия B19 (187 x 127 x 227 мм)» → [«B19», «187 x 127 x 227»]; значения без
     * типа и размеров вместе (числами, с ёмкостью) — null.
     *
     * @return array{string, string}|null
     */
    public static function splitTypeAndSize(string $value): ?array
    {
        return preg_match('/^(?:Азия|Евро)\s+([A-Z]{1,2}\d{1,2})\s*\(\s*(\d{2,3})\s*x\s*(\d{2,3})\s*x\s*(\d{2,3})\s*(?:мм)?\s*\)$/u', $value, $match) === 1
            ? [$match[1], "{$match[2]} x {$match[3]} x {$match[4]}"]
            : null;
    }
}
