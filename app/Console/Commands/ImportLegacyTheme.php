<?php

namespace App\Console\Commands;

use App\Enums\BannerPosition;
use App\Enums\MenuLocation;
use App\Enums\ProductSelection;
use App\Models\Banner;
use App\Models\MenuItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

#[Signature('import:legacy-theme')]
#[Description('Импорт оформления витрины из настроек темы UniShop2: меню, подвал, иконки категорий, баннеры')]
class ImportLegacyTheme extends LegacyImportCommand
{
    /** Домен старого сайта в абсолютных ссылках настроек. */
    private const LEGACY_HOSTS = ['https://start-stop.su', 'http://start-stop.su'];

    /** @var list<string> */
    private array $disabledUrls = [];

    protected function import(): int
    {
        $settings = json_decode((string) DB::connection('legacy')->table('oc_uni_setting')->value('data'), true);

        DB::transaction(function () use ($settings): void {
            MenuItem::query()->delete();

            $this->importTopLinks($settings['toplinks'] ?? []);
            $this->importMainMenu($settings['header']['headerlinks2'] ?? []);
            $this->importFooter($settings['footer_columns'] ?? []);
            $this->importBanners();
            $this->importProductSelections();
        });

        $icons = $this->importCategoryIcons($settings['menu']['first_level'] ?? []);
        $wall = $this->importCategoryWall();

        $this->info('Пунктов меню: '.MenuItem::query()->count().', баннеров: '.Banner::query()->count().", иконок категорий: {$icons}, разделов на стене: {$wall}");

        foreach (array_unique($this->disabledUrls) as $url) {
            $this->warn("Страницы {$url} на новом сайте пока нет — пункт меню выключен.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $links
     */
    private function importTopLinks(array $links): void
    {
        foreach (array_values($links) as $position => $link) {
            $this->createItem(MenuLocation::TopLinks, $this->text($link['title']), $this->text($link['link']), sortOrder: $position);
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $links
     */
    private function importMainMenu(array $links): void
    {
        foreach ($links as $link) {
            $item = $this->createItem(
                MenuLocation::Main,
                $this->text($link['title']),
                $this->text($link['link']),
                icon: $this->text($link['icon'] ?? null),
                sortOrder: (int) $this->text($link['sort_order'] ?? null),
            );

            foreach (array_values($link['children'] ?? []) as $position => $child) {
                $this->createItem(MenuLocation::Main, $this->text($child['title']), $this->text($child['link']), parent: $item, sortOrder: $position);
            }
        }
    }

    /**
     * Первая колонка («Информация») на старом сайте дополнялась
     * информационными страницами с флагом bottom — переносим их явными
     * пунктами, чтобы всё меню редактировалось в админке.
     *
     * @param  array<int|string, array<string, mixed>>  $columns
     */
    private function importFooter(array $columns): void
    {
        foreach (array_values($columns) as $position => $column) {
            // Пустая колонка в настройках — место под «Наши контакты», их тема
            // выводила сама; у нас они в шаблоне подвала.
            if ($this->text($column['heading'] ?? null) === '' && empty($column['links'])) {
                continue;
            }

            $heading = $this->createItem(MenuLocation::Footer, $this->text($column['heading']), null, sortOrder: $position);
            $childPosition = 0;

            if ($position === 0) {
                foreach (DB::table('pages')->where('status', true)->where('show_in_top', true)->orderBy('sort_order')->get() as $page) {
                    $this->createItem(MenuLocation::Footer, $page->title, '/page/'.$page->slug, parent: $heading, sortOrder: $childPosition++);
                }
            }

            foreach ($column['links'] ?? [] as $link) {
                $this->createItem(MenuLocation::Footer, $this->text($link['title']), $this->text($link['link']), parent: $heading, sortOrder: $childPosition++);
            }
        }
    }

    /**
     * Слайды Revolution Slider: каждый — одна картинка-слой со ссылкой.
     * Неопубликованные переносим выключенными — их можно включить в админке.
     */
    private function importBanners(): void
    {
        Banner::query()->delete();

        $sliders = [1 => BannerPosition::HomeSlider, 4 => BannerPosition::HomeStrip];

        $slides = DB::connection('legacy')
            ->table('oc_revslider_slides')
            ->whereIn('slider_id', array_keys($sliders))
            ->orderBy('slider_id')
            ->orderBy('slide_order')
            ->get();

        foreach ($slides as $slide) {
            $params = json_decode($slide->params, true) ?: [];
            $layers = json_decode($slide->layers, true) ?: [];
            $image = collect($layers)->pluck('image_url')->filter()->first();

            if (! $image) {
                continue;
            }

            Banner::query()->create([
                'position' => $sliders[$slide->slider_id],
                'image' => preg_replace('#^https?://[^/]+/image/#', '', $image),
                'url' => ($params['enable_link'] ?? 'false') === 'true' ? $this->localPath($params['link'] ?? null) : null,
                'sort_order' => $slide->slide_order,
                'is_active' => ($params['state'] ?? '') === 'published',
            ]);
        }
    }

    /**
     * «Рекомендуем» и «Акции» на главной — вкладки featured модулей
     * uni_five_in_one_v2 со списками товаров.
     */
    private function importProductSelections(): void
    {
        DB::table('product_selection')->delete();

        $selections = ['Рекомендуем' => ProductSelection::Recommended, 'Акции' => ProductSelection::Promo];
        $knownProductIds = DB::table('products')->pluck('id')->flip();

        $modules = DB::connection('legacy')->table('oc_module')->where('code', 'uni_five_in_one_v2')->pluck('setting');

        foreach ($modules as $setting) {
            $featured = json_decode($setting, true)['set']['featured'] ?? [];
            $selection = $selections[$this->text($featured['title'] ?? null)] ?? null;

            if ($selection === null || ($featured['status'] ?? '0') !== '1') {
                continue;
            }

            // Как модуль: берёт первые limit товаров списка (неактивные потом
            // отсеиваются на витрине) и выводит их по названию.
            $productIds = collect($featured['products'] ?? [])
                ->map(fn ($productId): int => (int) $productId)
                ->take((int) ($featured['limit'] ?? 0) ?: null)
                ->filter(fn (int $productId): bool => isset($knownProductIds[$productId]));

            DB::table('products')->whereIn('id', $productIds)->orderBy('name')->pluck('id')
                ->each(fn (int $productId, int $position) => DB::table('product_selection')->insertOrIgnore([
                    'selection' => $selection->value,
                    'product_id' => $productId,
                    'sort_order' => $position,
                ]));
        }
    }

    /**
     * «Популярные категории» (модуль uni_category_wall_v2): разделы и
     * выбранные под ними ссылки. Ссылки на бывшие категории-бренды
     * сопоставляем производителям тем же правилом склейки, что в import:legacy-brands.
     */
    private function importCategoryWall(): int
    {
        $setting = json_decode((string) DB::connection('legacy')->table('oc_module')->where('code', 'uni_category_wall_v2')->where('setting', 'like', '%"status":"1"%')->value('setting'), true);
        // Модуль сортирует разделы по своему sort_order; при равном — порядок в настройках.
        $wallCategories = collect($setting['categories'] ?? [])
            ->sortBy(fn (array $wallCategory): int => (int) ($wallCategory['sort_order'] ?? 0))
            ->all();

        DB::table('categories')->update(['home_wall_sort' => null, 'show_on_parent_wall' => false]);
        DB::table('category_wall_manufacturer')->delete();

        $legacyNames = DB::connection('legacy')->table('oc_category_description')->where('language_id', 1)->pluck('name', 'category_id');
        $position = 0;

        foreach ($wallCategories as $categoryId => $wallCategory) {
            if (! DB::table('categories')->where('id', $categoryId)->exists()) {
                continue;
            }

            DB::table('categories')->where('id', $categoryId)->update(['home_wall_sort' => $position++]);

            foreach (array_values($wallCategory['child'] ?? []) as $childPosition => $childId) {
                if (DB::table('categories')->where('id', $childId)->exists()) {
                    DB::table('categories')->where('id', $childId)->update(['show_on_parent_wall' => true]);

                    continue;
                }

                $name = trim(html_entity_decode((string) ($legacyNames[$childId] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $name = ImportLegacyBrands::BRAND_ALIASES[mb_strtoupper($name)] ?? $name;
                $manufacturerId = DB::table('manufacturers')->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->value('id');

                if ($manufacturerId) {
                    DB::table('category_wall_manufacturer')->insertOrIgnore([
                        'category_id' => $categoryId,
                        'manufacturer_id' => $manufacturerId,
                        'sort_order' => $childPosition,
                    ]);
                }
            }
        }

        return $position;
    }

    /**
     * Иконки пунктов меню категорий: картинка или класс Font Awesome.
     *
     * @param  array<int|string, array<string, mixed>>  $firstLevel
     */
    private function importCategoryIcons(array $firstLevel): int
    {
        $count = 0;

        foreach ($firstLevel as $categoryId => $settings) {
            $icon = $this->text($settings['icon']['img'] ?? null) ?: $this->text($settings['icon']['ico'] ?? null);

            // update() не считает строки, где значение не изменилось, — считаем сами.
            if ($icon && DB::table('categories')->where('id', $categoryId)->exists()) {
                DB::table('categories')->where('id', $categoryId)->update(['icon' => $icon]);
                $count++;
            }
        }

        return $count;
    }

    private function createItem(MenuLocation $location, string $title, ?string $url, ?string $icon = null, ?MenuItem $parent = null, int $sortOrder = 0): MenuItem
    {
        $path = $this->localPath($url);
        $isActive = $path === null || str_starts_with($path, 'http') || $this->routeExists(strtok($path, '?'));

        if (! $isActive) {
            $this->disabledUrls[] = $path;
        }

        return MenuItem::query()->create([
            'location' => $location,
            'parent_id' => $parent?->id,
            'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $path,
            'icon' => $icon ?: null,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Ссылка из настроек → адрес на новом сайте: «about_us» и
     * «https://start-stop.su/about_us» — это инфостраница /page/about_us.
     */
    private function localPath(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $path = trim(str_replace(self::LEGACY_HOSTS, '', trim($url)), '/');

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (DB::table('pages')->where('slug', $path)->exists()) {
            return '/page/'.$path;
        }

        // Категория «/akkumulyatori» и бывшая категория-бренд «/akkumulyatori/zubr»
        // (теперь — производитель): раздел с фильтром по производителю.
        $segments = explode('/', $path);

        if (DB::table('categories')->where('slug', $segments[0])->exists()) {
            $manufacturerId = isset($segments[1])
                ? DB::table('manufacturers')->where('slug', $segments[1])->value('id')
                : null;

            return '/category/'.$segments[0].($manufacturerId ? '?manufacturer[]='.$manufacturerId : '');
        }

        return '/'.$path;
    }

    private function routeExists(string $path): bool
    {
        return collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route): bool => in_array('GET', $route->methods(), true)
                && $route->matches(request()->create($path), includingMethod: false));
    }

    /**
     * Мультиязычное поле настроек: {"1": "текст"} → "текст".
     */
    private function text(mixed $value): string
    {
        return trim((string) (is_array($value) ? Arr::first($value) : $value));
    }
}
