<?php

namespace App\Providers;

use App\Console\Commands\LegacyImportCommand;
use App\Models\Category;
use App\View\Composers\BatteryFilterComposer;
use App\View\Composers\CartCountComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
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
        LegacyImportCommand::prohibit(! config('shop.legacy_import_enabled'));

        // Сайдбар категорий на странице категории и на главной.
        View::composer('partials.category-sidebar', function ($view): void {
            $view->with(
                'menuCategories',
                Category::whereNull('parent_id')->where('status', true)->orderBy('sort_order')->get(),
            );
        });

        View::composer('partials.header', HeaderComposer::class);
        View::composer('partials.footer', FooterComposer::class);
        View::composer('partials.header', CartCountComposer::class);

        View::composer('partials.battery-filter', BatteryFilterComposer::class);
    }
}
