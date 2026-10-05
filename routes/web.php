<?php

use App\Http\Controllers\BatteryFilterController;
use App\Http\Controllers\CallbackRequestController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\QuickOrderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', SearchController::class)->name('search');
Route::get('/catalog/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
// Адрес как на старом сайте (OpenCart information/contact).
Route::get('/contact-us', ContactController::class)->name('contact');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

// Адрес как на старом сайте (product/compare).
Route::get('/compare-products', [CompareController::class, 'index'])->name('compare.index');
Route::post('/compare-products/{product}', [CompareController::class, 'store'])->name('compare.store');
Route::delete('/compare-products/{product}', [CompareController::class, 'destroy'])->name('compare.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/callback', [CallbackRequestController::class, 'create'])->name('callback.create');
Route::post('/callback', [CallbackRequestController::class, 'store'])->middleware('throttle:5,1')->name('callback.store');

Route::get('/quick-order/{product}', [QuickOrderController::class, 'create'])->name('quick-order.create');
Route::post('/quick-order/{product}', [QuickOrderController::class, 'store'])->middleware('throttle:10,1')->name('quick-order.store');

Route::get('/product-question/{product}', [ProductQuestionController::class, 'create'])->name('product-question.create');
Route::post('/product-question/{product}', [ProductQuestionController::class, 'store'])->middleware('throttle:5,1')->name('product-question.store');

Route::get('/podbor-akb', [BatteryFilterController::class, 'show'])->name('battery-selection');

Route::prefix('battery-filter')->name('battery-filter.')->group(function () {
    Route::get('/models', [BatteryFilterController::class, 'getModels'])->name('models');
    Route::get('/generations', [BatteryFilterController::class, 'getGenerations'])->name('generations');
    Route::get('/result', [BatteryFilterController::class, 'getResult'])->name('result');
});
