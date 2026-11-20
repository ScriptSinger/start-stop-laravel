<?php

namespace App\Services\Seo;

use App\Models\Guide;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Support\Str;

/**
 * Теги <head> гайда: title и description, превью ссылки как у статьи и
 * микроразметка schema.org (Article с датами, BreadcrumbList) — по ней
 * Яндекс и Google показывают путь и дату обновления в выдаче.
 */
class GuideSeo
{
    public function __construct(private BreadcrumbsSeo $breadcrumbs) {}

    public function apply(Guide $guide): void
    {
        SEOTools::setTitle($guide->meta_title ?: $guide->title, appendDefault: ! $guide->meta_title);

        $description = $guide->meta_description ?: $this->description($guide);
        SEOTools::setDescription($description);

        OpenGraph::setType('article')
            ->setArticle([
                'published_time' => $guide->created_at?->toAtomString(),
                'modified_time' => $guide->updated_at?->toAtomString(),
            ]);

        JsonLdMulti::newJsonLd()
            ->setType('Article')
            ->setTitle($guide->title)
            ->setUrl(false)
            ->addValues([
                'headline' => Str::limit($guide->title, 110, ''),
                'description' => $description,
                'mainEntityOfPage' => route('guide.show', $guide),
                'datePublished' => $guide->created_at?->toAtomString(),
                'dateModified' => $guide->updated_at?->toAtomString(),
                'inLanguage' => 'ru',
                'author' => ['@type' => 'Organization', 'name' => config('shop.name')],
                'publisher' => ['@type' => 'Organization', 'name' => config('shop.name'), 'url' => url('/')],
            ]);

        $this->breadcrumbs->apply([
            ['name' => 'Главная', 'url' => route('home')],
            ['name' => $guide->title, 'url' => route('guide.show', $guide)],
        ]);
    }

    /**
     * Описание для сниппета, когда своего нет: начало текста гайда.
     */
    private function description(Guide $guide): string
    {
        $text = (string) Str::of(html_entity_decode((string) preg_replace('~<[^>]+>|\[\[[^\]]+\]\]~', ' ', (string) $guide->body), ENT_QUOTES | ENT_HTML5))
            ->replace("\u{00A0}", ' ')
            ->squish()
            ->limit(200, preserveWords: true);

        return $text !== '' ? $text : "{$guide->title} — советы магазина «".config('shop.name').'» в Уфе.';
    }
}
