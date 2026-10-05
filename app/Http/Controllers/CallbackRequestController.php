<?php

namespace App\Http\Controllers;

use App\Enums\CustomerRequestType;
use App\Http\Requests\CallbackRequestRequest;
use App\Models\CustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Заказать звонок»: окно в шапке и блок на главной. Заявка видна в админке.
 */
class CallbackRequestController extends Controller
{
    public function create(Request $request): View
    {
        return view($request->ajax() ? 'callback.form' : 'callback.page');
    }

    public function store(CallbackRequestRequest $request): JsonResponse|RedirectResponse
    {
        CustomerRequest::query()->create([
            'type' => CustomerRequestType::Callback,
            ...$request->validated(),
        ]);

        $message = 'Спасибо! Мы перезвоним вам в рабочее время.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('callback.create')->with('status', $message);
    }
}
