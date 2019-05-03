<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/

Route::group(['middleware' => ['api_secret_check']], function () {

  Route::post('api/v1/signup', 'AccountController@store')->name('signup.submit');
  Route::post('api/v1/login', 'Auth\LoginController@apiLogin')->name('login.submit');

});


Route::group(['middleware' => ['db','api_secret_check','token_auth'], 'prefix' =>'api/v1', 'as' => 'api.'], function () {

  Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit

  Route::post('clients/bulk', 'ClientController@bulk')->name('clients.bulk');

  Route::resource('invoices', 'InvoiceController'); // name = (invoices. index / create / show / update / destroy / edit

  Route::get('invoices/{invoice}/{action}', 'InvoiceController@action')->name('invoices.action');

  Route::post('invoices/bulk', 'InvoiceController@bulk')->name('invoices.bulk');

  Route::resource('products', 'ProductController'); // name = (products. index / create / show / update / destroy / edit

  Route::post('products/bulk', 'ProductController@bulk')->name('products.bulk');

  Route::resource('quotes', 'QuoteController'); // name = (quotes. index / create / show / update / destroy / edit

  Route::post('quotes/bulk', 'QuoteController@bulk')->name('quotes.bulk');

  Route::resource('recurring_invoices', 'RecurringInvoiceController'); // name = (recurring_invoices. index / create / show / update / destroy / edit

  Route::post('recurring_invoices/bulk', 'RecurringInvoiceController@bulk')->name('recurring_invoices.bulk');

  Route::resource('client_statement', 'ClientStatementController@statement'); // name = (client_statement. index / create / show / update / destroy / edit

  Route::resource('payments', 'PaymentController'); // name = (payments. index / create / show / update / destroy / edit
  
  Route::post('payments/bulk', 'PaymentController@bulk')->name('payments.bulk');

/*
  Route::resource('tasks', 'TaskController'); // name = (tasks. index / create / show / update / destroy / edit
  
  Route::post('tasks/bulk', 'TaskController@bulk')->name('tasks.bulk');
  
  
  Route::resource('credits', 'CreditController'); // name = (credits. index / create / show / update / destroy / edit
  
  Route::post('credits/bulk', 'CreditController@bulk')->name('credits.bulk');
  
  Route::resource('expenses', 'ExpenseController'); // name = (expenses. index / create / show / update / destroy / edit
  
  Route::post('expenses/bulk', 'ExpenseController@bulk')->name('expenses.bulk');
  
  Route::resource('user', 'UserProfileController'); // name = (clients. index / create / show / update / destroy / edit
  
  Route::get('settings', 'SettingsController@index')->name('user.settings');
*/
});


Route::fallback('BaseController@notFound');