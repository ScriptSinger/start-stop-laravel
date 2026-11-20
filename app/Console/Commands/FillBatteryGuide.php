<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Guide;
use App\Services\Catalog\BatteryGuideContent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('guides:fill-battery {--force : Записать; без флага — только отчёт} {--rewrite-body : Заменить текст и мета-теги уже созданного гайда на свежий черновик (правки в админке будут потеряны)}')]
#[Description('Добавить гайд «Как выбрать аккумулятор» и подсказки к характеристикам аккумуляторов (черновик для правки в админке)')]
class FillBatteryGuide extends Command
{
    /**
     * Только добавляет: существующий гайд, готовые подсказки и привязки к гайду
     * не меняются; текст гайда заменяется только с --rewrite-body. Характеристики и значения ищутся по названию. Перед
     * запуском: php artisan db:backup.
     */
    public function handle(): int
    {
        $guide = Guide::query()->where('slug', BatteryGuideContent::SLUG)->first();
        $rewrite = $guide !== null && $this->option('rewrite-body');
        $this->components->twoColumnDetail('Гайд «'.BatteryGuideContent::TITLE.'»', match (true) {
            $guide === null => 'будет создан',
            $rewrite => 'текст будет заменён',
            default => 'уже есть, не меняется',
        });

        $attributes = Attribute::query()->with('values')->get()->groupBy('name');
        $plan = ['attributes' => [], 'values' => []];

        foreach (BatteryGuideContent::attributeHints() as $name => [$hint, $anchor]) {
            foreach ($attributes->get($name, []) as $attribute) {
                if (blank($attribute->hint)) {
                    $plan['attributes'][] = [$attribute, $hint, $anchor];
                }
            }
        }

        foreach (BatteryGuideContent::valueHints() as $name => $values) {
            foreach ($attributes->get($name, []) as $attribute) {
                foreach ($attribute->values as $value) {
                    if (isset($values[$value->value]) && blank($value->hint)) {
                        $plan['values'][] = [$value, ...$values[$value->value]];
                    }
                }
            }
        }

        $this->components->twoColumnDetail('Подсказки к характеристикам', (string) count($plan['attributes']));
        $this->components->twoColumnDetail('Подсказки к значениям', (string) count($plan['values']));

        if (! $this->option('force')) {
            $this->warn('Пробный прогон: ничего не записано. Для записи добавьте --force.');

            return self::SUCCESS;
        }

        if ($rewrite) {
            $guide->update([
                'body' => BatteryGuideContent::body(),
                'meta_title' => BatteryGuideContent::META_TITLE,
                'meta_description' => BatteryGuideContent::META_DESCRIPTION,
            ]);
        }

        $guide ??= Guide::query()->create([
            'title' => BatteryGuideContent::TITLE,
            'slug' => BatteryGuideContent::SLUG,
            'body' => BatteryGuideContent::body(),
            'meta_title' => BatteryGuideContent::META_TITLE,
            'meta_description' => BatteryGuideContent::META_DESCRIPTION,
            'status' => true,
        ]);

        foreach ($plan['attributes'] as [$attribute, $hint, $anchor]) {
            $attribute->update([
                'hint' => $hint,
                'guide_id' => $attribute->guide_id ?? $guide->id,
                'guide_anchor' => $attribute->guide_anchor ?: $anchor,
            ]);
        }

        foreach ($plan['values'] as [$value, $hint, $anchor]) {
            $value->update(['hint' => $hint, 'guide_anchor' => $value->guide_anchor ?: $anchor]);
        }

        $this->info('Готово: гайд, подсказок к характеристикам — '.count($plan['attributes']).', к значениям — '.count($plan['values']).'.');

        return self::SUCCESS;
    }
}
