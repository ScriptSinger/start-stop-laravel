<?php

use App\Http\Controllers\BatteryFilterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/podbor-akb', [BatteryFilterController::class, 'show'])->name('battery-selection');

Route::prefix('battery-filter')->name('battery-filter.')->group(function () {
    Route::get('/models', [BatteryFilterController::class, 'getModels'])->name('models');
    Route::get('/generations', [BatteryFilterController::class, 'getGenerations'])->name('generations');
    Route::get('/result', [BatteryFilterController::class, 'getResult'])->name('result');
});
