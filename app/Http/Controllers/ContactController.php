<?php

namespace App\Http\Controllers;

use Artesaos\SEOTools\Facades\SEOTools;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(): View
    {
        SEOTools::setTitle('Связаться с нами');

        return view('contact');
    }
}
