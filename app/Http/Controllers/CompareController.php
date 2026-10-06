<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SavedProducts\Compare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CompareController extends Controller
{
    public function index(Compare $compare): View
    {
        $products = $compare->products();

        // Строки таблицы — все характеристики сравниваемых товаров в порядке карточки.
        $specificationNames = $products
            ->flatMap(fn (Product $product): Collection => $product->attributeValues->pluck('attribute'))
            ->unique('id')
            ->sortBy([['display_sort_order', 'asc'], ['name', 'asc']])
            ->pluck('name')
            ->values();

        return view('compare', ['products' => $products, 'specificationNames' => $specificationNames]);
    }

    public function store(Request $request, Compare $compare, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->status, 404);

        $compare->add($product);

        return $this->respond($request, $compare, "«{$product->name}» добавлен в сравнение.");
    }

    public function destroy(Request $request, Compare $compare, Product $product): JsonResponse|RedirectResponse
    {
        $compare->remove($product);

        return $this->respond($request, $compare, "«{$product->name}» удалён из сравнения.");
    }

    private function respond(Request $request, Compare $compare, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'count' => $compare->count(), 'ids' => $compare->productIds()])
            : back()->with('notice', $message);
    }
}
