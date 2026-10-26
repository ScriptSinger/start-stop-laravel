<?php

namespace Tests\Unit;

use App\Services\Supplier\BatteryNameFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BatteryNameFormatterTest extends TestCase
{
    /**
     * @return array<string, array{string, ?int, ?string, string}>
     */
    public static function names(): array
    {
        return [
            'АКБ АВТО: всё в названии' => ['BOLK Asia 6ст-50 Ач  пуск. ток 450, раз. 236*129*220 п.п.', null, null, 'Аккумулятор BOLK 50Ah п.п. 450A АЗИЯ 236x129x220'],
            'АКБ АВТО: код после габаритов' => ['FORGO 6ст-36 Ач  пуск. ток 480, раз. 195*127*200*220 п.п. азия 42B19RMF', null, null, 'Аккумулятор FORGO 36Ah п.п. 480A АЗИЯ 42B19RMF 195x127x200'],
            'АКБ АВТО: серия' => ['ZUBR ULTRA 6ст-62 Ач LB  пуск. ток 600, раз. 242*175*175 о.п.', null, null, 'Аккумулятор ZUBR ULTRA 62Ah о.п. 600A 242x175x175'],
            'АКБ АВТО: латинская c и запятые' => ['MUTLU Silver JIS 6cт-60 Ач, пус ток520, раз 232*173*225, о.п. 23L', null, null, 'Аккумулятор MUTLU Silver 60Ah о.п. 520A АЗИЯ 23L 232x173x225'],
            'АКБ АВТО: код слит с полярностью' => ['BORG STANDART ASIA 6cт-100 Ач, пус ток800 раз 306*173*225, п.п.125D31R', null, null, 'Аккумулятор BORG STANDART 100Ah п.п. 800A АЗИЯ 125D31R 306x173x225'],
            'Типоразмер в бренде: код переходит после тока, полярность по R' => ['SOLITE 95D26R (85ач Азия)', null, null, 'Аккумулятор SOLITE 85Ah п.п. АЗИЯ 95D26R'],
            'Типоразмер L: обратная' => ['SOLITE 75D23L (65ач Азия)', null, null, 'Аккумулятор SOLITE 65Ah о.п. АЗИЯ 75D23L'],
            'Низкий корпус не в бренде' => ['6ст - 85 Zubr Premium  низкий', null, null, 'Аккумулятор Zubr Premium 85Ah о.п. низкий'],
            'Ток 750А (русская А) не дублируется' => ['Аккумулятор TOPLA  Energy 6СТ 75 0(R+) 750А', null, null, 'Аккумулятор TOPLA Energy 75Ah п.п. 750A'],
            'Латинская o в о.п. и габариты с пробелами' => ['URSA BLUE  6ст-60 Ач  пуск. ток 460, раз. 242 x 175 x 190 o.п.', null, null, 'Аккумулятор URSA BLUE 60Ah о.п. 460A 242x175x190'],
            'Габариты с заглавной X' => ['ZUBR ULTRA 6ст-110 Ач  пуск. ток 950, раз. 353X175X190 п.п.', null, null, 'Аккумулятор ZUBR ULTRA 110Ah п.п. 950A 353x175x190'],
            'Сиб: ток и габариты из колонок' => ['AC/DC  100 пр (115D31R, KN)', 800, '302x175x200', 'Аккумулятор AC/DC 100Ah п.п. 800A 115D31R 302x175x200'],
            'Сиб: типоразмер L2.0 → L2L' => ['МЕДВЕДЬ 60 обр (L2.0, VLA)', 610, '242x175x190', 'Аккумулятор МЕДВЕДЬ 60Ah о.п. 610A L2L 242x175x190'],
            'Сиб: низкий корпус' => ['XTREME +EFB 74 обр (LB3.0, низк)', 780, '278x175x175', 'Аккумулятор XTREME +EFB 74Ah о.п. 780A LB3L низкий 278x175x175'],
            'Сиб: конус' => ['FireBall   225 обр (C.3, конус)', 1350, '518x274x240', 'Аккумулятор FireBall 225Ah о.п. 1350A Груз C конус 518x274x240'],
            'Сиб: мото' => ['Мото BUSHIDO HCZ20H-i (YT20-4, 22Ah) пр', 310, '177x88x155', 'Аккумулятор Мото BUSHIDO HCZ20H-i 22Ah п.п. 310A YT20-4 177x88x155'],
            'Дриллинг: только ёмкость и полярность' => ['Аккумулятор TOPLA  Top Sealed 6СТ  100 0(R+)', null, null, 'Аккумулятор TOPLA Top Sealed 100Ah п.п.'],
            'Дриллинг: обе полярности — без полярности в названии' => ['Аккумулятор UNICUM 60 оп/пп', null, null, 'Аккумулятор UNICUM 60Ah'],
            'Дриллинг: ёмкость в начале' => ['6ст - 60 Zubr Ultra', null, null, 'Аккумулятор Zubr Ultra 60Ah о.п.'],
        ];
    }

    #[DataProvider('names')]
    public function test_name_is_brought_to_one_c_style(string $source, ?int $cca, ?string $dimensions, string $expected): void
    {
        $this->assertSame($expected, (new BatteryNameFormatter)->format($source, $cca, $dimensions)['name']);
    }

    public function test_missing_data_is_reported_not_invented(): void
    {
        $result = (new BatteryNameFormatter)->format('Аккумулятор TOPLA Top Sealed 6СТ 100 0(R+)');

        $this->assertSame(['пусковой ток', 'габариты'], $result['missing']);
    }

    public function test_polarity_defaults_to_reverse_when_not_given(): void
    {
        $result = (new BatteryNameFormatter)->format('Аккумулятор Mutlu SFB M3 6СТ-63.0 низкий');

        $this->assertSame('Аккумулятор Mutlu SFB M3 63Ah о.п. низкий', $result['name']);
        $this->assertContains('полярность не указана — принята о.п.', $result['notes']);
        $this->assertNotContains('полярность', $result['missing']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notBatteries(): array
    {
        return [
            ['Зарядное устройство DAEWOO DW500 6/12 B'],
            ['Полироль чернитель шин ODIS/Tyre shining Cleaner 650мл (Ds6088)'],
            ['AC/DC security DC1226 (Terminal-L2)'],
            ['XTREME VRLA 12v   85Ah (OT85-12)'],
            ['Тестер АКБ ВА 101 (для авто, 12в АКБ, русский язык)'],
            ['Шампунь для бесконтактной мойки ODIS/Standart 20 кг (Ds0114)'],
            ['PERFECTIS Xtreme C3 5W40 SN/SM/CF 4л'],
        ];
    }

    #[DataProvider('notBatteries')]
    public function test_other_goods_are_skipped(string $source): void
    {
        $this->assertNull((new BatteryNameFormatter)->format($source));
    }
}
