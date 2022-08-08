<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClientPortal\ApplePayDomainController;
use App\Http\Controllers\Gateways\Checkout3dsController;
use App\Http\Controllers\Gateways\GoCardlessController;
use App\Http\Controllers\Gateways\Mollie3dsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WePayController;
use Illuminate\Support\Facades\Route;

//Auth::routes(['password.reset' => false]);

Route::get('/', [BaseController::class, 'flutterRoute'])->middleware('guest');
    // Route::get('self-update', [SelfUpdateController::class, 'update'])->middleware('guest');

Route::get('setup', [SetupController::class, 'index'])->middleware('guest');
Route::post('setup', [SetupController::class, 'doSetup'])->middleware('guest');
Route::get('update', [SetupController::class, 'update'])->middleware('guest');

Route::post('setup/check_db', [SetupController::class, 'checkDB'])->middleware('guest');
Route::post('setup/check_mail', [SetupController::class, 'checkMail'])->middleware('guest');
Route::post('setup/check_pdf', [SetupController::class, 'checkPdf'])->middleware('guest');

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->middleware('domain_db')->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->middleware(['domain_db', 'email_db'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('email_db')->name('password.update');

Route::get('wepay/signup/{token}', [WePayController::class, 'signup'])->name('wepay.signup');
Route::get('wepay/finished', [WePayController::class, 'finished'])->name('wepay.finished');

/*
 * Social authentication
 */

Route::get('auth/{provider}', [LoginController::class, 'redirectToProvider']);

/*
 * Inbound routes requiring DB Lookup
 */
Route::middleware('url_db')->group(function () {
    Route::get('/user/confirm/{confirmation_code}', [UserController::class, 'confirm']);
    Route::post('/user/confirm/{confirmation_code}', [UserController::class, 'confirmWithPassword']);
});

Route::get('stripe/signup/{token}', [StripeConnectController::class, 'initialize'])->name('stripe_connect.initialization');
Route::get('stripe/completed', [StripeConnectController::class, 'completed'])->name('stripe_connect.return');

Route::get('checkout/3ds_redirect/{company_key}/{company_gateway_id}/{hash}', [Checkout3dsController::class, 'index'])->middleware('domain_db')->name('checkout.3ds_redirect');
Route::get('mollie/3ds_redirect/{company_key}/{company_gateway_id}/{hash}', [Mollie3dsController::class, 'index'])->middleware('domain_db')->name('mollie.3ds_redirect');
Route::get('gocardless/ibp_redirect/{company_key}/{company_gateway_id}/{hash}', [GoCardlessController::class, 'ibpRedirect'])->middleware('domain_db')->name('gocardless.ibp_redirect');
Route::get('.well-known/apple-developer-merchantid-domain-association', [ApplePayDomainController::class, 'showAppleMerchantId']);
