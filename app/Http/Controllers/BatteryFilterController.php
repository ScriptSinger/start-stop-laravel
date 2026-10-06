<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\BatteryFitment;
use App\Services\BatterySelection;
use App\Services\CarLanding\CarGeneration;
use App\Services\CarLanding\CarLandingCatalog;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\SEOTools;
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

    /**
     * Для марок с посадочными страницами — поколения этих страниц: без
     * повторов и марки в подписи, каждое со ссылкой (url). Если поколение
     * одно, подбор сразу ведёт на страницу модели.
     */
    public function getGenerations(Request $request, BatterySelection $selection, CarLandingCatalog $landings): JsonResponse
    {
        $brand = $request->string('brand')->toString();
        $model = $request->string('model')->toString();
        $landing = $landings->modelFor($brand, $model);

        if ($landing === null) {
            return response()->json($selection->generations($brand, $model));
        }

        $generations = $landings->generations($landing);

        if ($generations->isEmpty()) {
            return response()->json([[
                'name' => $landing->fullName(),
                'image' => $selection->generationImage((string) $landing->fitments->first()->generation),
                'url' => $landing->url(),
            ]]);
        }

        return response()->json($generations->map(fn (CarGeneration $generation): array => [
            'name' => $generation->label,
            'image' => $selection->generationImage((string) $generation->fitments->first()->generation),
            'url' => $generation->url(),
        ]));
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

    public function show(CatalogFilterRequest $request, BatterySelection $selection, CarLandingCatalog $landings): View
    {
        $fitment = $this->findFitment($request, $selection);

        abort_unless($fitment?->hasSelectionData(), 404);

        SEOTools::setTitle('Аккумуляторы для '.$fitment->displayName());
        // Машину задают параметры адреса — они и есть страница. SEOMeta
        // выводит адрес как есть, поэтому & экранируем сами.
        SEOMeta::setCanonical(e(route('battery-selection', [
            'brand' => $fitment->brand,
            'model' => $fitment->model,
            'gen' => $fitment->generation ?: null,
        ])));

        $landing = $landings->modelFor((string) $fitment->brand, (string) $fitment->model);

        return view('battery-selection', [
            'fitment' => $fitment,
            'landing' => $landing,
            'products' => $selection->products($fitment, $request->sort(), $request->perPage())->withQueryString(),
            'sort' => $request->sort(),
            'perPage' => $request->perPage(),
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
