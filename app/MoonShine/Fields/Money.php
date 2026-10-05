<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use Closure;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\UI\Fields\Number;

/**
 * Денежное поле: в базе decimal(15,4) и каст decimal:4, поэтому Eloquent
 * отдаёт "9500.0000". В форме показываем "9500", в списке — "9 500 ₽";
 * копейки (если когда-нибудь появятся) не теряются.
 */
class Money extends Number
{
    public function __construct(Closure|string|null $label = null, ?string $column = null, ?Closure $formatted = null)
    {
        parent::__construct($label, $column, $formatted);

        // Иначе браузер не примет в поле цену с копейками (шаг по умолчанию 1).
        $this->step(0.01);
    }

    protected function reformatFilledValue(mixed $data): mixed
    {
        if (! is_numeric($data)) {
            return $data;
        }

        return str_contains((string) $data, '.')
            ? rtrim(rtrim((string) $data, '0'), '.')
            : (string) $data;
    }

    protected function resolvePreview(): Renderable|string
    {
        $value = $this->toValue();

        if (! is_numeric($value)) {
            return '';
        }

        $hasKopecks = fmod((float) $value, 1.0) !== 0.0;

        return number_format((float) $value, $hasKopecks ? 2 : 0, ',', ' ').' ₽';
    }
}
