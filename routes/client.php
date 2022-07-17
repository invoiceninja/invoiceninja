<?php

use Illuminate\Support\Facades\Route;

Route::get('client', 'Auth\ContactLoginController@showLoginForm')->name('client.catchall')->middleware(['domain_db', 'contact_account','locale']); //catch all

Route::get('client/login', 'Auth\ContactLoginController@showLoginForm')->name('client.login')->middleware(['domain_db', 'contact_account','locale']);
Route::post('client/login', 'Auth\ContactLoginController@login')->name('client.login.submit');

Route::get('client/register/{company_key?}', 'Auth\ContactRegisterController@showRegisterForm')->name('client.register')->middleware(['domain_db', 'contact_account', 'contact_register','locale']);
Route::post('client/register/{company_key?}', 'Auth\ContactRegisterController@register')->middleware(['domain_db', 'contact_account', 'contact_register', 'locale', 'throttle:10,1']);

Route::get('client/password/reset', 'Auth\ContactForgotPasswordController@showLinkRequestForm')->name('client.password.request')->middleware(['domain_db', 'contact_account','locale']);
Route::post('client/password/email', 'Auth\ContactForgotPasswordController@sendResetLinkEmail')->name('client.password.email')->middleware('locale');
Route::get('client/password/reset/{token}', 'Auth\ContactResetPasswordController@showResetForm')->name('client.password.reset')->middleware(['domain_db', 'contact_account','locale']);
Route::post('client/password/reset', 'Auth\ContactResetPasswordController@reset')->name('client.password.update')->middleware(['domain_db', 'contact_account','locale']);

Route::get('view/{entity_type}/{invitation_key}', 'ClientPortal\EntityViewController@index')->name('client.entity_view');
Route::get('view/{entity_type}/{invitation_key}/password', 'ClientPortal\EntityViewController@password')->name('client.entity_view.password');
Route::post('view/{entity_type}/{invitation_key}/password', 'ClientPortal\EntityViewController@handlePassword');
Route::post('set_password', 'ClientPortal\EntityViewController@handlePasswordSet')->name('client.set_password')->middleware('domain_db');

Route::get('tmp_pdf/{hash}', 'ClientPortal\TempRouteController@index')->name('tmp_pdf');

Route::get('client/key_login/{contact_key}', 'ClientPortal\ContactHashLoginController@login')->name('client.contact_login')->middleware(['domain_db','contact_key_login']);
Route::get('client/magic_link/{magic_link}', 'ClientPortal\ContactHashLoginController@magicLink')->name('client.contact_magic_link')->middleware(['domain_db','contact_key_login']);
Route::get('documents/{document_hash}', 'ClientPortal\DocumentController@publicDownload')->name('documents.public_download')->middleware(['domain_db']);
Route::get('error', 'ClientPortal\ContactHashLoginController@errorPage')->name('client.error');
Route::get('client/payment/{contact_key}/{payment_id}', 'ClientPortal\InvitationController@paymentRouter')->middleware(['domain_db','contact_key_login']);
Route::get('client/ninja/{contact_key}/{company_key}', 'ClientPortal\NinjaPlanController@index')->name('client.ninja_contact_login')->middleware(['domain_db']);
Route::post('client/ninja/trial_confirmation', 'ClientPortal\NinjaPlanController@trial_confirmation')->name('client.trial.response')->middleware(['domain_db']);

Route::group(['middleware' => ['auth:contact', 'locale', 'domain_db','check_client_existence'], 'prefix' => 'client', 'as' => 'client.'], function () {
    Route::get('dashboard', 'ClientPortal\DashboardController@index')->name('dashboard'); // name = (dashboard. index / create / show / update / destroy / edit

    Route::get('plan', 'ClientPortal\NinjaPlanController@plan')->name('plan'); // name = (dashboard. index / create / show / update / destroy / edit

    Route::get('invoices', 'ClientPortal\InvoiceController@index')->name('invoices.index')->middleware('portal_enabled');
    Route::post('invoices/payment', 'ClientPortal\InvoiceController@bulk')->name('invoices.bulk');
    Route::get('invoices/payment', 'ClientPortal\InvoiceController@catch_bulk')->name('invoices.catch_bulk');
    Route::post('invoices/download', 'ClientPortal\InvoiceController@download')->name('invoices.download');
    Route::get('invoices/{invoice}', 'ClientPortal\InvoiceController@show')->name('invoice.show');
    Route::get('invoices/{invoice_invitation}', 'ClientPortal\InvoiceController@show')->name('invoice.show_invitation');

    Route::get('recurring_invoices', 'ClientPortal\RecurringInvoiceController@index')->name('recurring_invoices.index')->middleware('portal_enabled');
    Route::get('recurring_invoices/{recurring_invoice}', 'ClientPortal\RecurringInvoiceController@show')->name('recurring_invoice.show');
    Route::get('recurring_invoices/{recurring_invoice}/request_cancellation', 'ClientPortal\RecurringInvoiceController@requestCancellation')->name('recurring_invoices.request_cancellation');

    Route::post('payments/process', 'ClientPortal\PaymentController@process')->name('payments.process');
    Route::get('payments/process', 'ClientPortal\PaymentController@catch_process')->name('payments.catch_process');

    Route::post('payments/credit_response', 'ClientPortal\PaymentController@credit_response')->name('payments.credit_response');

    Route::get('payments', 'ClientPortal\PaymentController@index')->name('payments.index')->middleware('portal_enabled');
    Route::get('payments/{payment}', 'ClientPortal\PaymentController@show')->name('payments.show');

    // Route::post('payments/process/response', 'ClientPortal\PaymentController@response')->name('payments.response');
    // Route::get('payments/process/response', 'ClientPortal\PaymentController@response')->name('payments.response.get');

    Route::get('profile/{client_contact}/edit', 'ClientPortal\ProfileController@edit')->name('profile.edit');
    Route::put('profile/{client_contact}/edit', 'ClientPortal\ProfileController@update')->name('profile.update');
    Route::put('profile/{client_contact}/edit_client', 'ClientPortal\ProfileController@updateClient')->name('profile.edit_client');
    Route::put('profile/{client_contact}/localization', 'ClientPortal\ProfileController@updateClientLocalization')->name('profile.edit_localization');

    Route::get('payment_methods/{payment_method}/verification', 'ClientPortal\PaymentMethodController@verify')->name('payment_methods.verification');
    Route::post('payment_methods/{payment_method}/verification', 'ClientPortal\PaymentMethodController@processVerification')->middleware(['throttle:10,1']);

    Route::get('payment_methods/confirm', 'ClientPortal\PaymentMethodController@store')->name('payment_methods.confirm');

    Route::resource('payment_methods', 'ClientPortal\PaymentMethodController')->except(['edit', 'update']);

    Route::match(['GET', 'POST'], 'quotes/approve', 'ClientPortal\QuoteController@bulk')->name('quotes.bulk');
    Route::get('quotes', 'ClientPortal\QuoteController@index')->name('quotes.index')->middleware('portal_enabled');
    Route::get('quotes/{quote}', 'ClientPortal\QuoteController@show')->name('quote.show');
    Route::get('quotes/{quote_invitation}', 'ClientPortal\QuoteController@show')->name('quote.show_invitation');
    Route::post('quotes/download', 'ClientPortal\QuoteController@download')->name('quotes.download');

    Route::get('credits', 'ClientPortal\CreditController@index')->name('credits.index');
    Route::get('credits/{credit}', 'ClientPortal\CreditController@show')->name('credit.show');

    Route::get('credits/{credit_invitation}', 'ClientPortal\CreditController@show')->name('credits.show_invitation');

    Route::get('client/switch_company/{contact}', 'ClientPortal\SwitchCompanyController')->name('switch_company');

    Route::post('documents/download_multiple', 'ClientPortal\DocumentController@downloadMultiple')->name('documents.download_multiple');
    Route::get('documents/{document}/download', 'ClientPortal\DocumentController@download')->name('documents.download');
    Route::resource('documents', 'ClientPortal\DocumentController')->only(['index', 'show']);

    Route::get('subscriptions/{recurring_invoice}/plan_switch/{target}', 'ClientPortal\SubscriptionPlanSwitchController@index')->name('subscription.plan_switch');

    Route::resource('subscriptions', 'ClientPortal\SubscriptionController')->middleware('portal_enabled')->only(['index']);

    Route::resource('tasks', 'ClientPortal\TaskController')->only(['index']);

    Route::get('statement', 'ClientPortal\StatementController@index')->name('statement');
    Route::get('statement/raw', 'ClientPortal\StatementController@raw')->name('statement.raw');

    Route::post('upload', 'ClientPortal\UploadController')->name('upload.store');
    Route::get('logout', 'Auth\ContactLoginController@logout')->name('logout');

});

Route::post('payments/process/response', 'ClientPortal\PaymentController@response')->name('client.payments.response')->middleware(['locale', 'domain_db', 'verify_hash']);
Route::get('payments/process/response', 'ClientPortal\PaymentController@response')->name('client.payments.response.get')->middleware(['locale', 'domain_db', 'verify_hash']);

Route::get('client/subscriptions/{subscription}/purchase', 'ClientPortal\SubscriptionPurchaseController@index')->name('client.subscription.purchase')->middleware('domain_db');

Route::group(['middleware' => ['invite_db'], 'prefix' => 'client', 'as' => 'client.'], function () {
    /*Invitation catches*/
    Route::get('recurring_invoice/{invitation_key}', 'ClientPortal\InvitationController@recurringRouter');
    Route::get('invoice/{invitation_key}', 'ClientPortal\InvitationController@invoiceRouter');
    Route::get('quote/{invitation_key}', 'ClientPortal\InvitationController@quoteRouter');
    Route::get('credit/{invitation_key}', 'ClientPortal\InvitationController@creditRouter');
    Route::get('recurring_invoice/{invitation_key}/download_pdf', 'RecurringInvoiceController@downloadPdf')->name('recurring_invoice.download_invitation_key');
    Route::get('invoice/{invitation_key}/download_pdf', 'InvoiceController@downloadPdf')->name('invoice.download_invitation_key');
    Route::get('quote/{invitation_key}/download_pdf', 'QuoteController@downloadPdf')->name('quote.download_invitation_key');
    Route::get('credit/{invitation_key}/download_pdf', 'CreditController@downloadPdf')->name('credit.download_invitation_key');
    Route::get('{entity}/{invitation_key}/download', 'ClientPortal\InvitationController@routerForDownload');
    Route::get('pay/{invitation_key}', 'ClientPortal\InvitationController@payInvoice')->name('pay.invoice');

    Route::get('unsubscribe/{entity}/{invitation_key}', 'ClientPortal\InvitationController@unsubscribe')->name('unsubscribe');

    // Route::get('{entity}/{client_hash}/{invitation_key}', 'ClientPortal\InvitationController@routerForIframe')->name('invoice.client_hash_and_invitation_key'); //should never need this

});

Route::get('phantom/{entity}/{invitation_key}', '\App\Utils\PhantomJS\Phantom@displayInvitation')->middleware(['invite_db', 'phantom_secret'])->name('phantom_view');

Route::fallback('BaseController@notFoundClient');
