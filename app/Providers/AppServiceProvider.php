<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\View\Composers\BatteryFilterComposer;
use App\View\Composers\CartCountComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Меню категорий нужно и в шапке, и в сайдбаре на разных страницах —
        // проще один раз прокинуть через composer на оба партиала напрямую,
        // чем гадать, успеет ли composer на layouts.app отработать раньше
        // @include внутри @section дочерней вьюхи (Blade это не гарантирует).
        View::composer(['partials.header', 'partials.category-sidebar'], function ($view): void {
            $view->with(
                'menuCategories',
                Category::whereNull('parent_id')->where('status', true)->orderBy('sort_order')->get(),
            );
        });

        // Верхняя тонкая полоска (bottom=1 в legacy: О компании, Политика
        // безопасности и т.д.) и нижний ряд меню (bottom=0: Услуги, Трейд-ин...)
        // — реальные страницы из oc_information, не выдумка.
        View::composer('partials.header', function ($view): void {
            $pages = Page::where('status', true)->orderBy('sort_order')->get();

            $view->with('topPages', $pages->where('show_in_top', true));
            $view->with('navPages', $pages->where('show_in_top', false));
        });

        View::composer('partials.header', CartCountComposer::class);

        View::composer('partials.battery-filter', BatteryFilterComposer::class);
    }
}
