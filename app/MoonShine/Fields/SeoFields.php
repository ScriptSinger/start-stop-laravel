<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * SEO-поля товара, категории и страницы: H1, title и description.
 * Пустые поля сайт заменяет названием — подсказки говорят, чем именно.
 */
class SeoFields
{
    /**
     * @return list<Text|Textarea>
     */
    public static function make(string $nameLabel = 'название'): array
    {
        return [
            Text::make('Заголовок H1', 'heading')
                ->nullable()
                ->hint("Заголовок на самой странице. Пусто — {$nameLabel}"),
            Text::make('Meta title', 'meta_title')
                ->nullable()
                ->hint("Заголовок вкладки и ссылки в поиске, выводится как есть; поисковики показывают около 60 символов. Пусто — «{$nameLabel} — ".config('shop.name').'»'),
            Textarea::make('Meta description', 'meta_description')
                ->nullable()
                ->hint('Текст под ссылкой в поиске, около 160 символов. Пусто — поисковик возьмёт текст со страницы сам'),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
