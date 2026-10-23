<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::controller(StorefrontController::class)->name('shop.')->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/men', 'men')->name('men');
    Route::get('/women', 'women')->name('women');
    Route::get('/boys', 'boys')->name('boys');
    Route::get('/girls', 'girls')->name('girls');
    Route::get('/new-arrivals', 'newArrivals')->name('new-arrivals');
    Route::get('/search', 'search')->name('search');
    Route::get('/wishlist', 'wishlist')->name('wishlist');
    Route::get('/product/{slug}', 'product')->name('product');
    Route::get('/product/{slug}/preview.jpg', 'productPreview')->name('product.preview');
});
