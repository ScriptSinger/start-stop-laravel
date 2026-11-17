<?php

namespace App\Services\Seo;

use Artesaos\SEOTools\Facades\JsonLdMulti;

/**
 * Микроразметка BreadcrumbList: те же крошки, что над заголовком страницы.
 * Яндекс и Google показывают по ней путь вместо адреса в сниппете.
 */
class BreadcrumbsSeo
{
    /**
     * @param  list<array{name: string, url: string}>  $crumbs  от главной к текущей странице
     */
    public function apply(array $crumbs): void
    {
        JsonLdMulti::newJsonLd()
            ->setType('BreadcrumbList')
            ->setUrl(false)
            ->addValue('itemListElement', array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)));
    }
}
