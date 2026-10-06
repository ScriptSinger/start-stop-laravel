<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BatteryFilterController;
use App\Http\Controllers\CallbackRequestController;
use App\Http\Controllers\CarLandingController;
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
Route::get('/search', SearchController::class)->middleware('noindex')->name('search');
Route::get('/catalog/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
// Сколько товаров будет с выбранным фильтром — для кнопки «Показать N товаров» на телефоне.
Route::get('/category/{category:slug}/count', [CategoryController::class, 'count'])->name('category.count');
// Адрес как на старом сайте (OpenCart information/contact).
Route::get('/contact-us', ContactController::class)->name('contact');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page.show');

// Личный кабинет покупателя (вход, регистрация, восстановление пароля — Fortify).
// Адреса как на старом сайте.
Route::middleware(['auth', 'noindex'])->group(function () {
    Route::get('/my-account', [AccountController::class, 'index'])->name('account');
    Route::get('/order-history', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/order-history/{order}', [AccountController::class, 'order'])->whereNumber('order')->name('account.order');
});

Route::get('/cart', [CartController::class, 'index'])->middleware('noindex')->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/wishlist', [WishlistController::class, 'index'])->middleware('noindex')->name('wishlist.index');
Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

// Адрес как на старом сайте (product/compare).
Route::get('/compare-products', [CompareController::class, 'index'])->middleware('noindex')->name('compare.index');
Route::post('/compare-products/{product}', [CompareController::class, 'store'])->name('compare.store');
Route::delete('/compare-products/{product}', [CompareController::class, 'destroy'])->name('compare.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->middleware('noindex')->name('checkout.success');

Route::get('/callback', [CallbackRequestController::class, 'create'])->middleware('noindex')->name('callback.create');
Route::post('/callback', [CallbackRequestController::class, 'store'])->middleware('throttle:5,1')->name('callback.store');

Route::get('/quick-order/{product}', [QuickOrderController::class, 'create'])->middleware('noindex')->name('quick-order.create');
Route::post('/quick-order/{product}', [QuickOrderController::class, 'store'])->middleware('throttle:10,1')->name('quick-order.store');

Route::get('/product-question/{product}', [ProductQuestionController::class, 'create'])->middleware('noindex')->name('product-question.create');
Route::post('/product-question/{product}', [ProductQuestionController::class, 'store'])->middleware('throttle:5,1')->name('product-question.store');

Route::get('/podbor-akb', [BatteryFilterController::class, 'show'])->name('battery-selection');

// Посадочные «Аккумулятор для <модель>» — марки включаются в shop.car_landings.
Route::get('/akkumulyator-dlya/{brand}', [CarLandingController::class, 'brand'])->name('car-landing.brand');
Route::get('/akkumulyator-dlya/{brand}/{model}', [CarLandingController::class, 'model'])->name('car-landing.model');
Route::get('/akkumulyator-dlya/{brand}/{model}/{generation}', [CarLandingController::class, 'generation'])->name('car-landing.generation');

Route::prefix('battery-filter')->name('battery-filter.')->group(function () {
    Route::get('/models', [BatteryFilterController::class, 'getModels'])->name('models');
    Route::get('/generations', [BatteryFilterController::class, 'getGenerations'])->name('generations');
    Route::get('/result', [BatteryFilterController::class, 'getResult'])->name('result');
});
