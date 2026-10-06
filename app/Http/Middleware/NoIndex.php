<?php

namespace App\Http\Middleware;

use Artesaos\SEOTools\Facades\SEOMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Служебные страницы (корзина, кабинет, поиск, формы) не нужны в поиске.
 * На старом сайте их закрывал robots.txt, у нас — meta robots: ссылки с них
 * поисковик по-прежнему обходит.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        SEOMeta::setRobots('noindex, follow');

        return $next($request);
    }
}
