<?php

namespace App\Enums;

/**
 * Результат сопоставления товара сайта с кодом 1С (products:link-onec).
 */
enum OneCLinkStatus: string
{
    /** Код из sku есть в прайсе 1С, названия в обоих прайсах сходятся. */
    case SameCode = 'same_code';

    /** В 1С у товара другой код: найден по названию из «Торгового дома». */
    case ByName = 'by_name';

    /** По названию в прайсе 1С нашлось несколько строк. */
    case Ambiguous = 'ambiguous';

    /** Есть в «Торговом доме», но в 1С такого названия нет. */
    case NotInOneC = 'not_in_onec';

    /** Кода нет ни в «Торговом доме», ни в 1С. */
    case Unknown = 'unknown';

    /** Код есть в обоих прайсах, но названия разные: скорее всего, разные товары. */
    case NameMismatch = 'name_mismatch';

    public function isLinked(): bool
    {
        return in_array($this, [self::SameCode, self::ByName], true);
    }

    public function toString(): string
    {
        return match ($this) {
            self::SameCode => 'тот же код в 1С',
            self::ByName => 'по названию из «Торгового дома»',
            self::Ambiguous => 'несколько совпадений в 1С по названию',
            self::NotInOneC => 'нет в 1С по названию',
            self::Unknown => 'нет ни в «Торговом доме», ни в 1С',
            self::NameMismatch => 'код совпал, названия разные',
        };
    }
}
