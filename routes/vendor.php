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
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClientPortal;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Auth\VendorContactLoginController;
use App\Http\Controllers\VendorPortal\InvitationController;
use App\Http\Controllers\VendorPortal\PurchaseOrderController;
use App\Http\Controllers\VendorPortal\VendorContactController;
use Illuminate\Support\Facades\Route;

Route::get('vendors', [VendorContactLoginController::class, 'catch'])->name('vendor.catchall')->middleware(['domain_db', 'contact_account', 'vendor_locale']); //catch all

Route::middleware('invite_db')->prefix('vendor')->name('vendor.')->group(function () {
    /*Invitation catches*/
    Route::get('purchase_order/{invitation_key}', [InvitationController::class, 'purchaseOrder']);
    //   Route::get('purchase_order/{invitation_key}/download_pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('recurring_invoice.download_invitation_key');
 //   Route::get('purchase_order/{invitation_key}/download', [ClientPortal\InvitationController::class, 'routerForDownload']);
});

Route::middleware('auth:vendor', 'vendor_locale', 'domain_db')->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('dashboard', [PurchaseOrderController::class, 'index'])->name('dashboard');
    Route::get('purchase_orders', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
    Route::get('purchase_orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase_order.show');

    Route::get('profile/{vendor_contact}/edit', [VendorContactController::class, 'edit'])->name('profile.edit');
    Route::put('profile/{vendor_contact}/edit', [VendorContactController::class, 'update'])->name('profile.update');

    Route::post('purchase_orders/bulk', [PurchaseOrderController::class, 'bulk'])->name('purchase_orders.bulk');
    Route::get('logout', [VendorContactLoginController::class, 'logout'])->name('logout');
});

Route::fallback([BaseController::class, 'notFoundVendor']);
