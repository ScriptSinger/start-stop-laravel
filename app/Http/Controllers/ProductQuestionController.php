<?php

namespace App\Http\Controllers;

use App\Enums\CustomerRequestType;
use App\Http\Requests\ProductQuestionRequest;
use App\Models\CustomerRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Задать вопрос» на странице товара (вкладка «Вопрос-ответ»): заявка
 * «Вопрос о товаре» в админке, ответ — по телефону.
 */
class ProductQuestionController extends Controller
{
    public function create(Request $request, Product $product): View
    {
        abort_unless($product->status, 404);

        return view($request->ajax() ? 'product-question.form' : 'product-question.page', ['product' => $product]);
    }

    public function store(ProductQuestionRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->status, 404);

        CustomerRequest::query()->create([
            'type' => CustomerRequestType::ProductQuestion,
            'product_id' => $product->id,
            ...$request->validated(),
        ]);

        $message = 'Спасибо за вопрос! Мы перезвоним и ответим.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('product.show', $product)->with('status', $message);
    }
}
