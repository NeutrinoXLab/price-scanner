<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', [ScannerController::class, 'index']);
    Route::post('/sync', [ScannerController::class, 'sync'])->middleware('throttle:2,1');
    Route::get('/offers/{offer}', [ScannerController::class, 'offer']);
    Route::post('/offers/{offer}/watch', [ScannerController::class, 'watch']);
    Route::delete('/watches/{watch}', [ScannerController::class, 'unwatch']);
    Route::post('/alerts/{id}/read', [ScannerController::class, 'read']);
    Route::post('/image', [ScannerController::class, 'image'])->middleware('throttle:5,1');
    Route::get('/export', [ScannerController::class, 'export']);
    Route::post('/offers/{offer}/canonical-product', [ProductController::class, 'createFromOffer']);
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/matches/{match}/review', [ProductController::class, 'review']);
    Route::post('/products/{product}/opportunity', [ProductController::class, 'opportunity']);
});
