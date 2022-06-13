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
use App\Http\Controllers\VendorPortal\InvitationController;
use App\Http\Controllers\VendorPortal\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['invite_db'], 'prefix' => 'vendor', 'as' => 'vendor.'], function () {
    /*Invitation catches*/
    Route::get('purchase_order/{invitation_key}', [InvitationController::class, 'purchaseOrder']);
 //   Route::get('purchase_order/{invitation_key}/download_pdf', 'PurchaseOrderController@downloadPdf')->name('recurring_invoice.download_invitation_key');
 //   Route::get('purchase_order/{invitation_key}/download', 'ClientPortal\InvitationController@routerForDownload');

});


Route::group(['middleware' => ['auth:vendor', 'vendor_locale', 'domain_db'], 'prefix' => 'vendor', 'as' => 'vendor.'], function () {

    Route::get('dashboard', [PurchaseOrderController::class, 'index'])->name('dashboard');
    Route::get('purchase_orders', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
    Route::get('purchase_orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase_order.show');

    Route::get('profile/{vendor_contact}/edit', [PurchaseOrderController::class, 'index'])->name('profile.edit');

});