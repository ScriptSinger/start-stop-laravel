<?php

namespace App\Providers;

use App\Console\Commands\LegacyImportCommand;
use App\View\Composers\BatteryFilterComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderCartComposer;
use App\View\Composers\HeaderComposer;
use App\View\Composers\SavedProductsCountComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
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

        // Разметка пагинации как у темы UniShop2 (ul.pagination Bootstrap 3).
        Paginator::useBootstrapThree();

        View::composer('partials.header', HeaderComposer::class);
        View::composer('partials.footer', FooterComposer::class);
        View::composer('partials.header', HeaderCartComposer::class);
        View::composer('partials.header', SavedProductsCountComposer::class);

        View::composer('partials.battery-filter', BatteryFilterComposer::class);

        $this->configureSeoDefaults();
    }

    /**
     * Название магазина в заголовке и превью ссылок и логотип как картинка
     * превью по умолчанию — из shop.php, чтобы не дублировать их в seotools.php.
     */
    private function configureSeoDefaults(): void
    {
        config([
            'seotools.meta.defaults.title' => config('shop.name'),
            'seotools.opengraph.defaults.site_name' => config('shop.name'),
            'seotools.opengraph.defaults.images' => [Storage::disk('public')->url(config('shop.logo'))],
        ]);
    }
}
