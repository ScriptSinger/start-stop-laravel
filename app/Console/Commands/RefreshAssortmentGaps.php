<?php

namespace App\Console\Commands;

use App\Services\Catalog\AssortmentGaps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assortment:gaps')]
#[Description('Посчитать машины, которым подбор не находит аккумулятор, и нужные им типоразмеры')]
class RefreshAssortmentGaps extends Command
{
    public function handle(AssortmentGaps $gaps): int
    {
        $summary = $gaps->refresh();

        $this->info("Машин без подходящего АКБ: {$summary['without_batteries']} из {$summary['cars']}");

        foreach (array_slice($summary['sizes'], 0, 5) as $size) {
            $this->line("  {$size['size']}, {$size['polarity']} — {$size['cars']}");
        }

        return self::SUCCESS;
    }
}
