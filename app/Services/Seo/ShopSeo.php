<?php

namespace App\Services\Seo;

use Artesaos\SEOTools\Facades\JsonLdMulti;
use Illuminate\Support\Facades\Storage;

/**
 * Микроразметка магазина (schema.org AutoPartsStore) для главной и страницы
 * контактов: адрес, телефон, координаты и режим работы идут в карточку
 * организации в Яндексе и Google, а значит и в локальную выдачу.
 */
class ShopSeo
{
    public function apply(string $description): void
    {
        [$latitude, $longitude] = config('shop.map_coordinates');

        JsonLdMulti::setType('AutoPartsStore')
            ->setTitle(config('shop.name'))
            ->setDescription($description)
            ->setUrl(route('home'))
            ->addValues(array_filter([
                'telephone' => config('shop.phone'),
                'email' => config('shop.email'),
                'logo' => Storage::disk('public')->url(config('shop.logo')),
                'image' => Storage::disk('public')->url(config('shop.logo')),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => config('shop.city'),
                    'streetAddress' => $this->street(),
                    'addressCountry' => 'RU',
                ],
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
                'openingHours' => array_map(
                    fn (array $hours): string => "{$hours['schema_days']} {$hours['from']}-{$hours['to']}",
                    config('shop.opening_hours'),
                ) ?: null,
                'sameAs' => array_column(config('shop.socials'), 'url') ?: null,
            ]));
    }

    /**
     * «г. Уфа, бульвар Тухвата Янаби, 36» → «бульвар Тухвата Янаби, 36».
     */
    private function street(): string
    {
        return trim(preg_replace('/^г\.\s*[^,]+,\s*/u', '', (string) config('shop.address')));
    }
}
