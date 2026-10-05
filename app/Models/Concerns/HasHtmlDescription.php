<?php

namespace App\Models\Concerns;

trait HasHtmlDescription
{
    /**
     * Есть ли в описании что показать: из OpenCart часто приходит пустая
     * разметка вроде "<p><br></p>", которая дала бы лишний отступ.
     * Картинка или видео без текста — тоже содержимое.
     */
    public function hasDescription(): bool
    {
        $content = strip_tags((string) $this->description, '<img><iframe><video>');

        return preg_replace('/[\s\x{00A0}]+/u', '', $content) !== '';
    }
}
