<?php

use App\Http\Controllers\BatteryFilterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page.show');

Route::get('/podbor-akb', [BatteryFilterController::class, 'show'])->name('battery-selection');

Route::prefix('battery-filter')->name('battery-filter.')->group(function () {
    Route::get('/models', [BatteryFilterController::class, 'getModels'])->name('models');
    Route::get('/generations', [BatteryFilterController::class, 'getGenerations'])->name('generations');
    Route::get('/result', [BatteryFilterController::class, 'getResult'])->name('result');
});
