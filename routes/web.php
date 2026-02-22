<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Bank\NordigenController;
use App\Http\Controllers\Bank\YodleeController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClientPortal\ApplePayDomainController;
use App\Http\Controllers\EInvoice\SelfhostController;
use App\Http\Controllers\Gateways\Checkout3dsController;
use App\Http\Controllers\Gateways\GoCardlessController;
use App\Http\Controllers\Gateways\GoCardlessOAuthController;
use App\Http\Controllers\Gateways\GoCardlessOAuthWebhookController;
use App\Http\Controllers\Gateways\Mollie3dsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BaseController::class, 'flutterRoute'])->middleware('guest');

Route::get('setup', [SetupController::class, 'index'])->middleware('guest');
Route::post('setup', [SetupController::class, 'doSetup'])->middleware('throttle:10,1')->middleware('guest');
Route::get('update', [SetupController::class, 'update'])->middleware('throttle:10,1')->middleware('guest');

Route::post('setup/check_db', [SetupController::class, 'checkDB'])->middleware('throttle:10,1')->middleware('guest');
Route::post('setup/check_mail', [SetupController::class, 'checkMail'])->middleware('throttle:10,1')->middleware('guest');
Route::post('setup/check_pdf', [SetupController::class, 'checkPdf'])->middleware('throttle:10,1')->middleware('guest');

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->middleware('domain_db')->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:10,1')->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->middleware(['domain_db', 'email_db'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('throttle:10,1')->middleware('email_db')->name('password.update');

Route::get('auth/{provider}', [LoginController::class, 'redirectToProvider']);

Route::middleware(['url_db'])->group(function () {
    Route::get('/user/confirm/{confirmation_code}', [UserController::class, 'confirm'])->middleware('throttle:10,1');
    Route::post('/user/confirm/{confirmation_code}', [UserController::class, 'confirmWithPassword'])->middleware('throttle:10,1');
});

Route::get('stripe/signup/{token}', [StripeConnectController::class, 'initialize'])->middleware('throttle:10,1')->name('stripe_connect.initialization');
Route::get('stripe/completed', [StripeConnectController::class, 'completed'])->middleware('throttle:10,1')->name('stripe_connect.return');

Route::get('yodlee/onboard/{token}', [YodleeController::class, 'auth'])->name('yodlee.auth');

Route::get('nordigen/connect/{token}', [NordigenController::class, 'connect'])->name('nordigen.connect');
Route::any('nordigen/confirm', [NordigenController::class, 'confirm'])->name('nordigen.confirm');

Route::get('checkout/3ds_redirect/{company_key}/{company_gateway_id}/{hash}', [Checkout3dsController::class, 'index'])->middleware('domain_db')->name('checkout.3ds_redirect');
Route::get('mollie/3ds_redirect/{company_key}/{company_gateway_id}/{hash}', [Mollie3dsController::class, 'index'])->middleware('domain_db')->name('mollie.3ds_redirect');
Route::get('gocardless/ibp_redirect/{company_key}/{company_gateway_id}/{hash}', [GoCardlessController::class, 'ibpRedirect'])->middleware('domain_db')->name('gocardless.ibp_redirect');
Route::get('.well-known/apple-developer-merchantid-domain-association', [ApplePayDomainController::class, 'showAppleMerchantId']);

Route::get('gocardless/oauth/connect/confirm', [GoCardlessOAuthController::class, 'confirm'])->name('gocardless.oauth.confirm');
Route::post('gocardless/oauth/connect/webhook', GoCardlessOAuthWebhookController::class)->name('gocardless.oauth.webhook');
Route::get('gocardless/oauth/connect/{token}', [GoCardlessOAuthController::class, 'connect']);

Route::redirect('buy_now', 'https://invoiceninja.invoicing.co/client/subscriptions/O5xe7Rwd7r/purchase', 301);

\Illuminate\Support\Facades\Broadcast::routes(['middleware' => ['token_auth']]);
