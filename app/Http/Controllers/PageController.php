<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Catalog\TradeInTable;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        // Таблица скидок на странице «Трейд-ин» строится из типов корпусов (см. TradeInTable).
        $content = TradeInTable::render((string) $page->description, always: $page->slug === config('shop.trade_in.page_slug'));

        SEOTools::setTitle($page->meta_title ?: $page->title, appendDefault: ! $page->meta_title);
        SEOTools::setDescription($page->meta_description ?: $this->description($page, $content));

        return view($page->slug === 'uslugi' ? 'services' : 'page', [
            'page' => $page,
            'content' => $content,
        ]);
    }

    /**
     * Описание для сниппета, когда своего нет: начало текста страницы, а у
     * страниц без текста — название и магазин.
     */
    private function description(Page $page, string $content): string
    {
        // «Услуги» выводят предложения из shop.service_offers, а не текст страницы.
        if ($page->slug === 'uslugi') {
            $titles = collect(config('shop.service_offers'))->pluck('title')->map(fn (string $title): string => mb_strtolower($title))->implode(', ');

            return Str::limit('Услуги магазина «'.config('shop.name')."» в Уфе: {$titles}.", 200, preserveWords: true);
        }

        $text = (string) Str::of(html_entity_decode(strip_tags((string) preg_replace('~\[\[[^\]]+\]\]~', ' ', $content)), ENT_QUOTES | ENT_HTML5))
            ->replace("\u{00A0}", ' ')
            ->squish()
            ->limit(200, preserveWords: true);

        return $text !== '' ? $text : "{$page->title} — магазин «".config('shop.name').'» в Уфе.';
    }
}
