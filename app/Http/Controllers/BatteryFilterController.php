<?php

namespace App\Http\Controllers;

use App\Enums\BannerPosition;
use App\Http\Requests\CatalogFilterRequest;
use App\Models\Banner;
use App\Models\BatteryFitment;
use App\Services\BatterySelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Шаги подбора АКБ (JSON для блока на главной) и страница результата.
 * Логика — в BatterySelection.
 */
class BatteryFilterController extends Controller
{
    public function getModels(Request $request, BatterySelection $selection): JsonResponse
    {
        return response()->json(
            $selection->models($request->string('brand')->toString())
                ->map(fn (string $model): array => ['model' => $model]),
        );
    }

    public function getGenerations(Request $request, BatterySelection $selection): JsonResponse
    {
        return response()->json($selection->generations(
            $request->string('brand')->toString(),
            $request->string('model')->toString(),
        ));
    }

    public function getResult(Request $request, BatterySelection $selection): JsonResponse
    {
        $fitment = $this->findFitment($request, $selection);

        if (! $fitment?->hasSelectionData()) {
            return response()->json([]);
        }

        return response()->json([
            'redirect' => route('battery-selection', [
                'brand' => $fitment->brand,
                'model' => $fitment->model,
                'gen' => $fitment->generation ?: null,
            ]),
        ]);
    }

    public function show(CatalogFilterRequest $request, BatterySelection $selection): View
    {
        $fitment = $this->findFitment($request, $selection);

        abort_unless($fitment?->hasSelectionData(), 404);

        return view('battery-selection', [
            'fitment' => $fitment,
            'products' => $selection->products($fitment, $request->sort(), $request->perPage())->withQueryString(),
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
            // Баннеры — как над товарами в категории, куда вёл подбор на старом сайте.
            'sliderBanners' => Banner::query()->shownAt(BannerPosition::HomeSlider)->get(),
            'stripBanners' => Banner::query()->shownAt(BannerPosition::HomeStrip)->get(),
        ]);
    }

    private function findFitment(Request $request, BatterySelection $selection): ?BatteryFitment
    {
        return $selection->findFitment(
            $request->string('brand')->toString(),
            $request->string('model')->toString(),
            $request->string('gen')->toString(),
        );
    }
}
