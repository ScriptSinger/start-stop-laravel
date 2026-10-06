<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        SEOTools::setTitle($page->meta_title ?: $page->title, appendDefault: ! $page->meta_title);
        if (filled($page->meta_description)) {
            SEOTools::setDescription($page->meta_description);
        }

        return view('page', ['page' => $page]);
    }
}
