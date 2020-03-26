<?php

Route::get('ping', 'AccountApiController@ping');
Route::post('login', 'AccountApiController@login');
Route::post('oauth_login', 'AccountApiController@oauthLogin');
Route::post('register', 'AccountApiController@register');
Route::get('static', 'AccountApiController@getStaticData');
Route::get('accounts', 'AccountApiController@show');
Route::put('accounts', 'AccountApiController@update');
Route::post('refresh', 'AccountApiController@refresh');
Route::post('upgrade', 'AccountApiController@upgrade');

Route::resource('clients', 'ClientApiController');
Route::resource('contacts', 'ContactApiController');
Route::get('quotes', 'QuoteApiController@index');
Route::get('download/{invoice_id}', 'InvoiceApiController@download');
Route::resource('invoices', 'InvoiceApiController');
Route::resource('payments', 'PaymentApiController');
Route::resource('tasks', 'TaskApiController');
Route::resource('credits', 'CreditApiController');
Route::post('hooks', 'IntegrationController@subscribe');
Route::delete('hooks/{subscription_id}', 'IntegrationController@unsubscribe');
Route::post('email_invoice', 'InvoiceApiController@emailInvoice');
Route::get('user_accounts', 'AccountApiController@getUserAccounts');
Route::resource('products', 'ProductApiController');
Route::resource('projects', 'ProjectApiController');
Route::resource('tax_rates', 'TaxRateApiController');
Route::resource('users', 'UserApiController');
Route::resource('expenses', 'ExpenseApiController');
Route::post('add_token', 'AccountApiController@addDeviceToken');
Route::post('remove_token', 'AccountApiController@removeDeviceToken');
Route::post('update_notifications', 'AccountApiController@updatePushNotifications');
Route::get('dashboard', 'DashboardApiController@index');
Route::resource('documents', 'DocumentAPIController');
Route::resource('vendors', 'VendorApiController');
Route::resource('expense_categories', 'ExpenseCategoryApiController');
Route::post('ios_subscription_status', 'AccountApiController@iosSubscriptionStatus');
Route::resource('payment_terms', 'PaymentTermApiController');
