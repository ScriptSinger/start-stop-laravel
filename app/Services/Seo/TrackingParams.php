<?php

namespace App\Services\Seo;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Метки рекламы и аналитики в адресе (utm_*, yclid…). Редирект на новый
 * адрес должен их сохранять, иначе Метрика потеряет источник визита и клик
 * из Директа не свяжется с заказом. Остальные параметры при редиректе
 * со старого адреса отбрасываются.
 */
class TrackingParams
{
    /** @var list<string> */
    private const NAMES = ['yclid', 'ysclid', 'gclid', 'fbclid', 'openstat', '_openstat', 'roistat', 'from'];

    /**
     * @return array<string, mixed>
     */
    public static function from(Request $request): array
    {
        return array_filter(
            $request->query(),
            fn (string $name): bool => Str::startsWith($name, 'utm_') || in_array($name, self::NAMES, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
