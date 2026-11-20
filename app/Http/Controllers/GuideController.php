<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Services\Catalog\GuideFigures;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class GuideController extends Controller
{
    public function show(Guide $guide): View
    {
        abort_unless($guide->status, 404);

        SEOTools::setTitle($guide->meta_title ?: $guide->title, appendDefault: ! $guide->meta_title);
        SEOTools::setDescription($guide->meta_description ?: $this->description($guide));

        return view('guide', [
            'guide' => $guide,
            'body' => GuideFigures::render((string) $guide->body),
            'sections' => $guide->sections(),
        ]);
    }

    /**
     * Описание для сниппета, когда своего нет: начало текста гайда.
     */
    private function description(Guide $guide): string
    {
        $text = (string) Str::of(html_entity_decode(strip_tags((string) preg_replace('~\[\[figure:[a-z0-9-]+\]\]~', '', (string) $guide->body)), ENT_QUOTES | ENT_HTML5))
            ->replace("\u{00A0}", ' ')
            ->squish()
            ->limit(200, preserveWords: true);

        return $text !== '' ? $text : "{$guide->title} — советы магазина «".config('shop.name').'» в Уфе.';
    }
}
