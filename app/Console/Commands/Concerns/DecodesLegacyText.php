<?php

namespace App\Console\Commands\Concerns;

trait DecodesLegacyText
{
    /**
     * OpenCart при сохранении кодирует текст из админки в HTML-сущности
     * (&lt;p&gt;, &quot;) и раскодирует только при выводе. Без этого описания
     * показывались бы тегами текстом, а названия — с &quot; вместо кавычек.
     */
    protected function legacyText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
