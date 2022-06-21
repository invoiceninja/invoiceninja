<?php

use App\Http\Controllers\Auth;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClientPortal;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('client', [Auth\ContactLoginController::class, 'showLoginForm'])->name('client.catchall')->middleware(['domain_db', 'contact_account', 'locale']); //catch all

Route::get('client/login', [Auth\ContactLoginController::class, 'showLoginForm'])->name('client.login')->middleware(['domain_db', 'contact_account', 'locale']);
Route::post('client/login', [Auth\ContactLoginController::class, 'login'])->name('client.login.submit');

Route::get('client/register/{company_key?}', [Auth\ContactRegisterController::class, 'showRegisterForm'])->name('client.register')->middleware(['domain_db', 'contact_account', 'contact_register', 'locale']);
Route::post('client/register/{company_key?}', [Auth\ContactRegisterController::class, 'register'])->middleware(['domain_db', 'contact_account', 'contact_register', 'locale', 'throttle:10,1']);

Route::get('client/password/reset', [Auth\ContactForgotPasswordController::class, 'showLinkRequestForm'])->name('client.password.request')->middleware(['domain_db', 'contact_account', 'locale']);
Route::post('client/password/email', [Auth\ContactForgotPasswordController::class, 'sendResetLinkEmail'])->name('client.password.email')->middleware('locale');
Route::get('client/password/reset/{token}', [Auth\ContactResetPasswordController::class, 'showResetForm'])->name('client.password.reset')->middleware(['domain_db', 'contact_account', 'locale']);
Route::post('client/password/reset', [Auth\ContactResetPasswordController::class, 'reset'])->name('client.password.update')->middleware(['domain_db', 'contact_account', 'locale']);

Route::get('view/{entity_type}/{invitation_key}', [ClientPortal\EntityViewController::class, 'index'])->name('client.entity_view');
Route::get('view/{entity_type}/{invitation_key}/password', [ClientPortal\EntityViewController::class, 'password'])->name('client.entity_view.password');
Route::post('view/{entity_type}/{invitation_key}/password', [ClientPortal\EntityViewController::class, 'handlePassword']);
Route::post('set_password', [ClientPortal\EntityViewController::class, 'handlePasswordSet'])->name('client.set_password')->middleware('domain_db');

Route::get('tmp_pdf/{hash}', [ClientPortal\TempRouteController::class, 'index'])->name('tmp_pdf');

Route::get('client/key_login/{contact_key}', [ClientPortal\ContactHashLoginController::class, 'login'])->name('client.contact_login')->middleware(['domain_db', 'contact_key_login']);
Route::get('client/magic_link/{magic_link}', [ClientPortal\ContactHashLoginController::class, 'magicLink'])->name('client.contact_magic_link')->middleware(['domain_db', 'contact_key_login']);
Route::get('documents/{document_hash}', [ClientPortal\DocumentController::class, 'publicDownload'])->name('documents.public_download')->middleware(['domain_db']);
Route::get('error', [ClientPortal\ContactHashLoginController::class, 'errorPage'])->name('client.error');
Route::get('client/payment/{contact_key}/{payment_id}', [ClientPortal\InvitationController::class, 'paymentRouter'])->middleware(['domain_db', 'contact_key_login']);
Route::get('client/ninja/{contact_key}/{company_key}', [ClientPortal\NinjaPlanController::class, 'index'])->name('client.ninja_contact_login')->middleware(['domain_db']);
Route::post('client/ninja/trial_confirmation', [ClientPortal\NinjaPlanController::class, 'trial_confirmation'])->name('client.trial.response')->middleware(['domain_db']);

Route::middleware('auth:contact', 'locale', 'domain_db', 'check_client_existence')->prefix('client')->name('client.')->group(function () {
    Route::get('dashboard', [ClientPortal\DashboardController::class, 'index'])->name('dashboard'); // name = (dashboard. index / create / show / update / destroy / edit

    Route::get('plan', [ClientPortal\NinjaPlanController::class, 'plan'])->name('plan'); // name = (dashboard. index / create / show / update / destroy / edit

    Route::get('invoices', [ClientPortal\InvoiceController::class, 'index'])->name('invoices.index')->middleware('portal_enabled');
    Route::post('invoices/payment', [ClientPortal\InvoiceController::class, 'bulk'])->name('invoices.bulk');
    Route::get('invoices/payment', [ClientPortal\InvoiceController::class, 'catch_bulk'])->name('invoices.catch_bulk');
    Route::post('invoices/download', [ClientPortal\InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('invoices/{invoice}', [ClientPortal\InvoiceController::class, 'show'])->name('invoice.show');
    Route::get('invoices/{invoice_invitation}', [ClientPortal\InvoiceController::class, 'show'])->name('invoice.show_invitation');

    Route::get('recurring_invoices', [ClientPortal\RecurringInvoiceController::class, 'index'])->name('recurring_invoices.index')->middleware('portal_enabled');
    Route::get('recurring_invoices/{recurring_invoice}', [ClientPortal\RecurringInvoiceController::class, 'show'])->name('recurring_invoice.show');
    Route::get('recurring_invoices/{recurring_invoice}/request_cancellation', [ClientPortal\RecurringInvoiceController::class, 'requestCancellation'])->name('recurring_invoices.request_cancellation');

    Route::post('payments/process', [ClientPortal\PaymentController::class, 'process'])->name('payments.process');
    Route::get('payments/process', [ClientPortal\PaymentController::class, 'catch_process'])->name('payments.catch_process');

    Route::post('payments/credit_response', [ClientPortal\PaymentController::class, 'credit_response'])->name('payments.credit_response');

    Route::get('payments', [ClientPortal\PaymentController::class, 'index'])->name('payments.index')->middleware('portal_enabled');
    Route::get('payments/{payment}', [ClientPortal\PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/process/response', [ClientPortal\PaymentController::class, 'response'])->name('payments.response');
    Route::get('payments/process/response', [ClientPortal\PaymentController::class, 'response'])->name('payments.response.get');

    Route::get('profile/{client_contact}/edit', [ClientPortal\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/{client_contact}/edit', [ClientPortal\ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/{client_contact}/edit_client', [ClientPortal\ProfileController::class, 'updateClient'])->name('profile.edit_client');
    Route::put('profile/{client_contact}/localization', [ClientPortal\ProfileController::class, 'updateClientLocalization'])->name('profile.edit_localization');

    Route::get('payment_methods/{payment_method}/verification', [ClientPortal\PaymentMethodController::class, 'verify'])->name('payment_methods.verification');
    Route::post('payment_methods/{payment_method}/verification', [ClientPortal\PaymentMethodController::class, 'processVerification'])->middleware(['throttle:10,1']);

    Route::get('payment_methods/confirm', [ClientPortal\PaymentMethodController::class, 'store'])->name('payment_methods.confirm');

    Route::resource('payment_methods', ClientPortal\PaymentMethodController::class)->except(['edit', 'update']);

    Route::match(['GET', 'POST'], 'quotes/approve', [ClientPortal\QuoteController::class, 'bulk'])->name('quotes.bulk');
    Route::get('quotes', [ClientPortal\QuoteController::class, 'index'])->name('quotes.index')->middleware('portal_enabled');
    Route::get('quotes/{quote}', [ClientPortal\QuoteController::class, 'show'])->name('quote.show');
    Route::get('quotes/{quote_invitation}', [ClientPortal\QuoteController::class, 'show'])->name('quote.show_invitation');
    Route::post('quotes/download', [ClientPortal\QuoteController::class, 'download'])->name('quotes.download');

    Route::get('credits', [ClientPortal\CreditController::class, 'index'])->name('credits.index');
    Route::get('credits/{credit}', [ClientPortal\CreditController::class, 'show'])->name('credit.show');

    Route::get('credits/{credit_invitation}', [ClientPortal\CreditController::class, 'show'])->name('credits.show_invitation');

    Route::get('client/switch_company/{contact}', ClientPortal\SwitchCompanyController::class)->name('switch_company');

    Route::post('documents/download_multiple', [ClientPortal\DocumentController::class, 'downloadMultiple'])->name('documents.download_multiple');
    Route::get('documents/{document}/download', [ClientPortal\DocumentController::class, 'download'])->name('documents.download');
    Route::resource('documents', ClientPortal\DocumentController::class)->only(['index', 'show']);

    Route::get('subscriptions/{recurring_invoice}/plan_switch/{target}', [ClientPortal\SubscriptionPlanSwitchController::class, 'index'])->name('subscription.plan_switch');

    Route::resource('subscriptions', ClientPortal\SubscriptionController::class)->middleware('portal_enabled')->only(['index']);

    Route::resource('tasks', ClientPortal\TaskController::class)->only(['index']);

    Route::get('statement', [ClientPortal\StatementController::class, 'index'])->name('statement');
    Route::get('statement/raw', [ClientPortal\StatementController::class, 'raw'])->name('statement.raw');

    Route::post('upload', ClientPortal\UploadController::class)->name('upload.store');
    Route::get('logout', [Auth\ContactLoginController::class, 'logout'])->name('logout');
});

Route::get('client/subscriptions/{subscription}/purchase', [ClientPortal\SubscriptionPurchaseController::class, 'index'])->name('client.subscription.purchase')->middleware('domain_db');

Route::middleware('invite_db')->prefix('client')->name('client.')->group(function () {
    /*Invitation catches*/
    Route::get('recurring_invoice/{invitation_key}', [ClientPortal\InvitationController::class, 'recurringRouter']);
    Route::get('invoice/{invitation_key}', [ClientPortal\InvitationController::class, 'invoiceRouter']);
    Route::get('quote/{invitation_key}', [ClientPortal\InvitationController::class, 'quoteRouter']);
    Route::get('credit/{invitation_key}', [ClientPortal\InvitationController::class, 'creditRouter']);
    Route::get('recurring_invoice/{invitation_key}/download_pdf', [RecurringInvoiceController::class, 'downloadPdf'])->name('recurring_invoice.download_invitation_key');
    Route::get('invoice/{invitation_key}/download_pdf', [InvoiceController::class, 'downloadPdf'])->name('invoice.download_invitation_key');
    Route::get('quote/{invitation_key}/download_pdf', [QuoteController::class, 'downloadPdf'])->name('quote.download_invitation_key');
    Route::get('credit/{invitation_key}/download_pdf', [CreditController::class, 'downloadPdf'])->name('credit.download_invitation_key');
    Route::get('{entity}/{invitation_key}/download', [ClientPortal\InvitationController::class, 'routerForDownload']);
    Route::get('pay/{invitation_key}', [ClientPortal\InvitationController::class, 'payInvoice'])->name('pay.invoice');

    Route::get('unsubscribe/{entity}/{invitation_key}', [ClientPortal\InvitationController::class, 'unsubscribe'])->name('unsubscribe');

    // Route::get('{entity}/{client_hash}/{invitation_key}', [ClientPortal\InvitationController::class, 'routerForIframe'])->name('invoice.client_hash_and_invitation_key'); //should never need this
});

Route::get('phantom/{entity}/{invitation_key}', [\App\Utils\PhantomJS\Phantom::class, 'displayInvitation'])->middleware(['invite_db', 'phantom_secret'])->name('phantom_view');

Route::fallback([BaseController::class, 'notFoundClient']);
