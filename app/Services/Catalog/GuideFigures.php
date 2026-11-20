<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Vite;

/**
 * Иллюстрации гайдов: в тексте гайда (админка → Гайды) ставится метка
 * [[figure:имя]], при показе она заменяется картинкой с подписью. Файлы лежат в
 * resources/images/guide и идут через Vite, поэтому в тексте их адреса не хранятся.
 */
class GuideFigures
{
    /**
     * @return array<string, array{file: string, alt: string, caption: string, width: int, height: int}>
     */
    public static function all(): array
    {
        return [
            'terminals' => [
                'file' => 'terminals.svg',
                'alt' => 'Схема: стандартные, тонкие и боковые клеммы аккумулятора',
                'caption' => 'Основные типы клемм',
                'width' => 840,
                'height' => 360,
            ],
            'label' => [
                'file' => 'label.svg',
                'alt' => 'Схема этикетки аккумулятора с подписанными напряжением, ёмкостью, пусковым током, полярностью и технологией',
                'caption' => 'Что где написано на этикетке',
                'width' => 840,
                'height' => 420,
            ],
            'date' => [
                'file' => 'date.svg',
                'alt' => 'Схема: отметка с датой выпуска на корпусе аккумулятора',
                'caption' => 'Где искать дату выпуска',
                'width' => 840,
                'height' => 340,
            ],
        ];
    }

    /**
     * Заменяет метки [[figure:имя]] (в том числе обёрнутые редактором в <p>)
     * на иллюстрации; неизвестные метки убирает.
     */
    public static function render(string $body): string
    {
        $figures = self::all();

        return (string) preg_replace_callback(
            '~(?:<p>\s*)?\[\[figure:([a-z0-9-]+)\]\](?:\s*</p>)?~u',
            function (array $match) use ($figures): string {
                $figure = $figures[$match[1]] ?? null;

                if ($figure === null) {
                    return '';
                }

                return sprintf(
                    '<figure class="guide-figure"><img class="guide-figure__img" src="%s" alt="%s" width="%d" height="%d" loading="lazy"><figcaption class="guide-figure__caption">%s</figcaption></figure>',
                    e(Vite::asset('resources/images/guide/'.$figure['file'])),
                    e($figure['alt']),
                    $figure['width'],
                    $figure['height'],
                    e($figure['caption']),
                );
            },
            $body,
        );
    }
}
