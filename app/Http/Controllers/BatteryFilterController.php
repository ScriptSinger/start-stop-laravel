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
                'image' => $selection->generationImage($landing->fitments->first()->displayName(withEngine: false)),
                'url' => $landing->url(),
                'engines' => false,
            ]]);
        }

        return response()->json($generations->map(fn (CarGeneration $generation): array => [
            'name' => $generation->label,
            'image' => $selection->generationImage($generation->fitments->first()->displayName(withEngine: false)),
            'url' => $generation->url(),
            // Моторам поколения нужны разные АКБ — сначала выбор двигателя.
            'engines' => $selection->engines($brand, $model, (string) $generation->fitments->first()->generation)->isNotEmpty(),
        ]));
    }

    /**
     * Двигатели поколения — только если им нужны разные АКБ, иначе пусто.
     */
    public function getEngines(Request $request, BatterySelection $selection): JsonResponse
    {
        return response()->json($selection->engines(
            $request->string('brand')->toString(),
            $request->string('model')->toString(),
            $request->string('gen')->toString(),
        ));
    }

    public function getResult(Request $request, BatterySelection $selection): JsonResponse
    {
        $fitment = $this->findFitment($request, $selection);

        if (! $fitment?->hasSelectionData()) {
            return response()->json([]);
        }

        return response()->json(['redirect' => $this->resultUrl($fitment)]);
    }

    public function show(CatalogFilterRequest $request, BatterySelection $selection, CarLandingCatalog $landings): View
    {
        $fitment = $this->findFitment($request, $selection);

        abort_unless($fitment?->hasSelectionData(), 404);

        SEOTools::setTitle('Аккумуляторы для '.$fitment->displayName());
        // Машину задают параметры адреса — они и есть страница. SEOMeta
        // выводит адрес как есть, поэтому & экранируем сами.
        SEOMeta::setCanonical(e($this->resultUrl($fitment)));

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
            $request->string('engine')->toString(),
        );
    }

    private function resultUrl(BatteryFitment $fitment): string
    {
        return route('battery-selection', [
            'brand' => $fitment->brand,
            'model' => $fitment->model,
            'gen' => $fitment->generation ?: null,
            'engine' => $fitment->engine ?: null,
        ]);
    }
}
