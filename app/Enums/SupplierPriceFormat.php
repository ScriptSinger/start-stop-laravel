<?php

namespace App\Enums;

/**
 * Формат прайса поставщика: от него зависит, в каких колонках лежат
 * название, код, закупочная цена и остаток.
 */
enum SupplierPriceFormat: string
{
    /** «АКБ АВТО»: коды УТ-…, цена «основной склад», остаток есть. */
    case AkbAuto = 'akb_auto';

    /** «СибЭлектроИсточник»: только название, цена «Оптовая (>200)», остаток есть. */
    case SibElectro = 'sib_electro';

    /** «АКБ Дриллинг»: название и цена, остатков нет. */
    case Drilling = 'drilling';

    public function label(): string
    {
        return match ($this) {
            self::AkbAuto => 'АКБ АВТО (коды УТ, остатки)',
            self::SibElectro => 'СибЭлектроИсточник (остатки)',
            self::Drilling => 'АКБ Дриллинг (без остатков)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $format): array => [$format->value => $format->label()])->all();
    }
}
