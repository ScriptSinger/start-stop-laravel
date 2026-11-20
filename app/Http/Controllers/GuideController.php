<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Services\Catalog\GuideFigures;
use App\Services\Catalog\GuideLinks;
use App\Services\Seo\GuideSeo;
use Illuminate\Contracts\View\View;

class GuideController extends Controller
{
    public function show(Guide $guide, GuideSeo $seo, GuideLinks $links): View
    {
        abort_unless($guide->status, 404);

        $seo->apply($guide);

        return view('guide', [
            'guide' => $guide,
            'body' => GuideFigures::render($links->render((string) $guide->body)),
            'sections' => $guide->sections(),
        ]);
    }
}
