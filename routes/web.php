<?php

use Illuminate\Support\Facades\Route;

//Auth::routes(['password.reset' => false]);

Route::get('/', 'BaseController@flutterRoute')->middleware('guest');
    // Route::get('self-update', 'SelfUpdateController@update')->middleware('guest');

Route::get('setup', 'SetupController@index')->middleware('guest');
Route::post('setup', 'SetupController@doSetup')->middleware('guest');
Route::get('update', 'SetupController@update')->middleware('guest');

Route::post('setup/check_db', 'SetupController@checkDB')->middleware('guest');
Route::post('setup/check_mail', 'SetupController@checkMail')->middleware('guest');
Route::post('setup/check_pdf', 'SetupController@checkPdf')->middleware('guest');

Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->middleware('domain_db')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->middleware(['domain_db','email_db'])->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset')->middleware('email_db')->name('password.update');

Route::get('wepay/signup/{token}', 'WePayController@signup')->name('wepay.signup');
Route::get('wepay/finished', 'WePayController@finished')->name('wepay.finished');

/*
 * Social authentication
 */

Route::get('auth/{provider}', 'Auth\LoginController@redirectToProvider');
// Route::get('auth/{provider}/create', 'Auth\LoginController@redirectToProviderAndCreate');

/*
 * Inbound routes requiring DB Lookup
 */
Route::group(['middleware' => ['url_db']], function () {
    Route::get('/user/confirm/{confirmation_code}', 'UserController@confirm');
    Route::post('/user/confirm/{confirmation_code}', 'UserController@confirmWithPassword');
});

Route::get('stripe/signup/{token}', 'StripeConnectController@initialize')->name('stripe_connect.initialization');
Route::get('stripe/completed', 'StripeConnectController@completed')->name('stripe_connect.return');

Route::get('checkout/3ds_redirect/{company_key}/{company_gateway_id}/{hash}', 'Gateways\Checkout3dsController@index')->name('checkout.3ds_redirect');
