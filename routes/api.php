<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PreviewPurchaseOrderController;

Route::group(['middleware' => ['throttle:300,1', 'api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {

    Route::post('preview', [PreviewController::class, 'show'])->name('preview.show');
    Route::post('live_preview', [PreviewController::class, 'live'])->name('preview.live');

    Route::post('preview/purchase_order', [PreviewPurchaseOrderController::class, 'show'])->name('preview_purchase_order.show');
    Route::post('live_preview/purchase_order', [PreviewPurchaseOrderController::class, 'live'])->name('preview_purchase_order.live');
});

Route::fallback([BaseController::class, 'notFound'])->middleware('throttle:404');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is healthy',
    ]);
})->middleware('throttle:20,1');
