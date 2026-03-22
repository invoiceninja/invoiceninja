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
use App\Http\Controllers\Auth\VendorContactLoginController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\VendorPortal\InvitationController;
use App\Http\Controllers\VendorPortal\PurchaseOrderController;
use App\Http\Controllers\VendorPortal\UploadController;
use App\Http\Controllers\VendorPortal\VendorContactController;
use App\Http\Controllers\VendorPortal\VendorContactHashLoginController;
use Illuminate\Support\Facades\Route;

Route::get('vendors', [VendorContactLoginController::class, 'catch'])->name('vendor.catchall')->middleware(['domain_db', 'contact_account','vendor_locale']); //catch all
Route::get('vendor/key_login/{contact_key}', [VendorContactHashLoginController::class, 'login'])->name('contact_login')->middleware(['domain_db','vendor_contact_key_login']);

Route::group(['middleware' => ['invite_db'], 'prefix' => 'vendor', 'as' => 'vendor.'], function () {
    /*Invitation catches*/
    Route::get('purchase_order/{invitation_key}', [InvitationController::class, 'purchaseOrder']);
    Route::get('purchase_order/{invitation_key}/download', [InvitationController::class, 'download']);//->middleware('token_auth');
});

Route::group(['middleware' => ['auth:vendor', 'vendor_locale', 'domain_db'], 'prefix' => 'vendor', 'as' => 'vendor.'], function () {

    Route::get('dashboard', [PurchaseOrderController::class, 'index'])->name('dashboard');
    Route::get('purchase_orders', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
    Route::get('purchase_orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase_order.show');

    Route::get('showBlob/{hash}', [PurchaseOrderController::class, 'showBlob'])->name('purchase_order.showBlob');

    Route::get('profile/{vendor_contact}/edit', [VendorContactController::class, 'edit'])->name('profile.edit');
    Route::put('profile/{vendor_contact}/edit', [VendorContactController::class, 'update'])->name('profile.update');
    Route::get('purchase_order/{invitation_key}/download_e_purchase_order', [App\Http\Controllers\PurchaseOrderController::class, 'downloadEPurchaseOrder'])->name('purchase_order.download_e_purchase_order')->middleware('token_auth');

    Route::post('purchase_orders/bulk', [PurchaseOrderController::class, 'bulk'])->name('purchase_orders.bulk');
    Route::get('logout', [VendorContactLoginController::class, 'logout'])->name('logout');
    Route::post('purchase_order/upload/{purchase_order}', [UploadController::class,'upload'])->name('upload.store');

    Route::post('documents/download_multiple', [App\Http\Controllers\VendorPortal\DocumentController::class, 'downloadMultiple'])->name('documents.download_multiple');
    Route::get('documents/{document}/download', [App\Http\Controllers\VendorPortal\DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/download_pdf', [App\Http\Controllers\VendorPortal\DocumentController::class, 'download'])->name('documents.download_pdf');
    
    Route::resource('documents', App\Http\Controllers\VendorPortal\DocumentController::class)->only(['index', 'show']);
    
});


Route::fallback([BaseController::class, 'notFoundVendor']);
