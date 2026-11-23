<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressSuggestionRequest;
use App\Services\Address\AddressSuggestions;
use Illuminate\Http\JsonResponse;

class AddressSuggestionController extends Controller
{
    /**
     * Варианты адреса по набранному началу: {"suggestions": ["г Уфа, ул Рихарда Зорге, д 63", …]}.
     */
    public function __invoke(AddressSuggestionRequest $request, AddressSuggestions $suggestions): JsonResponse
    {
        return response()->json(['suggestions' => $suggestions->suggest($request->string('query')->toString())]);
    }
}
