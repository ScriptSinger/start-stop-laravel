<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SavedProducts\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Wishlist $wishlist): View
    {
        return view('wishlist', ['products' => $wishlist->products()]);
    }

    public function store(Request $request, Wishlist $wishlist, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->status, 404);

        $wishlist->add($product);

        return $this->respond($request, $wishlist, "«{$product->name}» добавлен в закладки.");
    }

    public function destroy(Request $request, Wishlist $wishlist, Product $product): JsonResponse|RedirectResponse
    {
        $wishlist->remove($product);

        return $this->respond($request, $wishlist, "«{$product->name}» удалён из закладок.");
    }

    private function respond(Request $request, Wishlist $wishlist, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'count' => $wishlist->count()])
            : back()->with('notice', $message);
    }
}
