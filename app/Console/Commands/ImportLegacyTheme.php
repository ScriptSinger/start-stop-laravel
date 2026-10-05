<?php

namespace App\Console\Commands;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

#[Signature('import:legacy-theme')]
#[Description('Импорт оформления витрины из настроек темы UniShop2: меню над шапкой, главное меню, подвал, иконки категорий')]
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
        });

        $icons = $this->importCategoryIcons($settings['menu']['first_level'] ?? []);

        $this->info('Пунктов меню: '.MenuItem::query()->count().", иконок категорий: {$icons}");

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
        $isActive = $path === null || str_starts_with($path, 'http') || $this->routeExists($path);

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
