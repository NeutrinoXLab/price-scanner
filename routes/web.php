<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\ManualOfferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', [ScannerController::class, 'index'])->name('home');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/offers/{offer}', [ScannerController::class, 'offer'])->name('offers.show');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product}/opportunity', [ProductController::class, 'opportunity'])->middleware('throttle:10,1');
    Route::post('/offers/manual', [ManualOfferController::class, 'store'])->name('offers.manual.store');
    Route::get('/imports/csv', [CsvImportController::class, 'create'])->name('imports.csv.create');
    Route::post('/imports/csv/preview', [CsvImportController::class, 'preview'])->middleware('throttle:5,1')->name('imports.csv.preview');
    Route::post('/imports/csv', [CsvImportController::class, 'store'])->middleware('throttle:5,1')->name('imports.csv.store');
    Route::post('/sync', [ScannerController::class, 'sync'])->middleware('throttle:2,1');
    Route::post('/offers/{offer}/watch', [ScannerController::class, 'watch']);
    Route::delete('/watches/{watch}', [ScannerController::class, 'unwatch']);
    Route::post('/alerts/{id}/read', [ScannerController::class, 'read']);
    Route::post('/image', [ScannerController::class, 'image'])->middleware('throttle:5,1');
    Route::get('/export', [ScannerController::class, 'export'])->middleware('throttle:5,1')->name('export');
    Route::post('/offers/{offer}/canonical-product', [ProductController::class, 'createFromOffer']);
    Route::post('/matches/{match}/review', [ProductController::class, 'review']);
});
