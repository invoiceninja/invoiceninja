<?php

Route::get('client', 'Auth\ContactLoginController@showLoginForm')->name('client.login'); //catch all

Route::get('client/login', 'Auth\ContactLoginController@showLoginForm')->name('client.login');
Route::post('client/login', 'Auth\ContactLoginController@login')->name('client.login.submit');

Route::get('client/password/reset', 'Auth\ContactForgotPasswordController@showLinkRequestForm')->name('client.password.request');
Route::post('client/password/email', 'Auth\ContactForgotPasswordController@sendResetLinkEmail')->name('client.password.email');
Route::get('client/password/reset/{token}', 'Auth\ContactResetPasswordController@showResetForm')->name('client.password.reset');
Route::post('client/password/reset', 'Auth\ContactResetPasswordController@reset')->name('client.password.update');

//todo implement domain DB
//Route::group(['middleware' => ['auth:contact', 'domain_db'], 'prefix' => 'client', 'as' => 'client.'], function () {
Route::group(['middleware' => ['auth:contact'], 'prefix' => 'client', 'as' => 'client.'], function () {

	Route::get('dashboard', 'ClientPortal\DashboardController@index')->name('dashboard'); // name = (dashboard. index / create / show / update / destroy / edit

	Route::get('invoices', 'ClientPortal\InvoiceController@index')->name('invoices.index'); 
	Route::post('invoices/payment', 'ClientPortal\InvoiceController@bulk')->name('invoices.bulk');
	Route::get('invoices/{invoice}', 'ClientPortal\InvoiceController@show')->name('invoice.show');
	Route::get('invoices/{invoice_invitation}', 'ClientPortal\InvoiceController@show')->name('invoice.show_invitation');

	Route::get('recurring_invoices', 'ClientPortal\RecurringInvoiceController@index')->name('recurring_invoices.index'); 
	
	Route::get('payments', 'ClientPortal\PaymentController@index')->name('payments.index'); 
	Route::post('payments/process', 'ClientPortal\PaymentController@process')->name('payments.process');
	Route::post('payments/process/response', 'ClientPortal\PaymentController@response')->name('payments.response');

	Route::get('profile/{client_contact}/edit', 'ClientPortal\ProfileController@edit')->name('profile.edit');
	Route::put('profile/{client_contact}/edit', 'ClientPortal\ProfileController@update')->name('profile.update');
	Route::put('profile/{client_contact}/edit_client', 'ClientPortal\ProfileController@updateClient')->name('profile.edit_client');
	Route::put('profile/{client_contact}/localization', 'ClientPortal\ProfileController@updateClientLocalization')->name('profile.edit_localization');

	Route::resource('payment_methods', 'ClientPortal\PaymentMethodController');// name = (payment_methods. index / create / show / update / destroy / edit

	Route::post('document', 'ClientPortal\DocumentController@store')->name('document.store');
	Route::delete('document', 'ClientPortal\DocumentController@destroy')->name('document.destroy');
	
	Route::get('logout', 'Auth\ContactLoginController@logout')->name('logout');

});

Route::group(['middleware' => ['domain_db'], 'prefix' => 'client', 'as' => 'client.'], function () {

	/*Invitation catches*/
	Route::get('invoice/{invitation_id}','ClientPortal\InvitationController@invoiceRouter');

});

Route::fallback('BaseController@notFoundClient');