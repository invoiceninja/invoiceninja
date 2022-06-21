<?php

use App\Http\Controllers\Shop;
use Illuminate\Support\Facades\Route;

Route::middleware('company_key_db', 'locale')->prefix('api/v1')->group(function () {
    Route::get('shop/products', [Shop\ProductController::class, 'index']);
    Route::post('shop/clients', [Shop\ClientController::class, 'store']);
    Route::post('shop/invoices', [Shop\InvoiceController::class, 'store']);
    Route::get('shop/client/{contact_key}', [Shop\ClientController::class, 'show']);
    Route::get('shop/invoice/{invitation_key}', [Shop\InvoiceController::class, 'show']);
    Route::get('shop/product/{product_key}', [Shop\ProductController::class, 'show']);
    Route::get('shop/profile', [Shop\ProfileController::class, 'show']);
});
