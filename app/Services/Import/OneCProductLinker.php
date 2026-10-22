<?php

namespace App\Services\Import;

use App\Enums\OneCLinkStatus;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Сопоставление товаров сайта с кодами 1С. На сайте в sku лежит код «Торгового
 * дома», а в 1С у тех же товаров часто другой код (серия «РТ-…»). Мост — название:
 * в «Торговом доме» и в 1С оно одинаковое.
 *
 * Цепочка: sku → строка прайса «Торгового дома» → её название → строка прайса 1С.
 * Если такой же код уже есть в прайсе 1С, берётся он, но только когда названия
 * в обоих прайсах похожи: иначе это разные товары с одним номером.
 */
class OneCProductLinker
{
    /**
     * Доля общих слов, начиная с которой названия считаются одним товаром.
     */
    private const SAME_NAME_RATIO = 0.6;

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<array{external_id: string, name: string, price: ?float, quantity: ?float}>  $oneCRows
     * @param  list<array{external_id: string, name: string, price: ?float, quantity: ?float}>  $tradeHouseRows
     * @return Collection<int, array{product: Product, status: OneCLinkStatus, external_id: ?string, trade_house_name: string, onec_name: string}>
     */
    public function link(Collection $products, array $oneCRows, array $tradeHouseRows): Collection
    {
        $oneC = collect($oneCRows)->keyBy('external_id');
        $tradeHouse = collect($tradeHouseRows)->keyBy('external_id');
        $oneCByName = $oneC->groupBy(fn (array $row): string => $this->normalize($row['name']));

        return $products->map(function (Product $product) use ($oneC, $tradeHouse, $oneCByName): array {
            $code = trim((string) $product->sku);
            $tradeHouseRow = $tradeHouse->get($code);
            $result = fn (OneCLinkStatus $status, ?array $oneCRow = null): array => [
                'product' => $product,
                'status' => $status,
                'external_id' => $status->isLinked() ? $oneCRow['external_id'] : null,
                'trade_house_name' => $tradeHouseRow['name'] ?? '',
                'onec_name' => $oneCRow['name'] ?? '',
            ];

            if ($oneC->has($code)) {
                $oneCRow = $oneC[$code];

                return $tradeHouseRow !== null && ! $this->sameProduct($tradeHouseRow['name'], $oneCRow['name'])
                    ? $result(OneCLinkStatus::NameMismatch, $oneCRow)
                    : $result(OneCLinkStatus::SameCode, $oneCRow);
            }

            if ($tradeHouseRow === null) {
                return $result(OneCLinkStatus::Unknown);
            }

            $matches = $oneCByName->get($this->normalize($tradeHouseRow['name']), collect());

            return match ($matches->count()) {
                0 => $result(OneCLinkStatus::NotInOneC),
                1 => $result(OneCLinkStatus::ByName, $matches->first()),
                default => $result(OneCLinkStatus::Ambiguous),
            };
        })->values();
    }

    private function normalize(string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name)));
    }

    private function sameProduct(string $first, string $second): bool
    {
        if ($this->normalize($first) === $this->normalize($second)) {
            return true;
        }

        $firstWords = $this->words($first);
        $secondWords = $this->words($second);
        $smaller = min(count($firstWords), count($secondWords));

        return $smaller > 0 && count(array_intersect($firstWords, $secondWords)) / $smaller >= self::SAME_NAME_RATIO;
    }

    /**
     * @return list<string>
     */
    private function words(string $name): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name)), $matches);

        return array_values(array_unique($matches[0]));
    }
}
