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
    Route::post('api/v1/oauth_login', 'Auth\LoginController@oauthApiLogin');

});

Route::group(['api_secret_check', 'email_db'], function () {

    Route::post('api/v1/login', 'Auth\LoginController@apiLogin')->name('login.submit');
    Route::post('api/v1/reset_password', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.reset');

});

Route::group(['middleware' => ['api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {

    Route::resource('activities', 'ActivityController'); // name = (clients. index / create / show / update / destroy / edit

    Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit

    Route::post('clients/bulk', 'ClientController@bulk')->name('clients.bulk');

    Route::resource('invoices', 'InvoiceController'); // name = (invoices. index / create / show / update / destroy / edit
    
    Route::get('invoices/{invoice}/{action}', 'InvoiceController@action')->name('invoices.action');
    
    Route::post('invoices/bulk', 'InvoiceController@bulk')->name('invoices.bulk');

    Route::resource('credits', 'CreditController'); // name = (credits. index / create / show / update / destroy / edit

    Route::get('credits/{credit}/{action}', 'CreditController@action')->name('credits.action');

    Route::post('credits/bulk', 'CreditController@bulk')->name('credits.bulk');

    Route::resource('products', 'ProductController'); // name = (products. index / create / show / update / destroy / edit

    Route::post('products/bulk', 'ProductController@bulk')->name('products.bulk');

    Route::resource('quotes', 'QuoteController'); // name = (quotes. index / create / show / update / destroy / edit

    Route::post('quotes/bulk', 'QuoteController@bulk')->name('quotes.bulk');

    Route::resource('recurring_invoices', 'RecurringInvoiceController'); // name = (recurring_invoices. index / create / show / update / destroy / edit

    Route::post('recurring_invoices/bulk', 'RecurringInvoiceController@bulk')->name('recurring_invoices.bulk');

    Route::resource('recurring_quotes', 'RecurringQuoteController'); // name = (recurring_invoices. index / create / show / update / destroy / edit

    Route::post('recurring_quotes/bulk', 'RecurringQuoteController@bulk')->name('recurring_quotes.bulk');

    Route::resource('expenses', 'ExpenseController'); // name = (expenses. index / create / show / update / destroy / edit

    Route::post('expenses/bulk', 'ExpenseController@bulk')->name('expenses.bulk');

    Route::resource('vendors', 'VendorController'); // name = (vendors. index / create / show / update / destroy / edit

    Route::post('vendors/bulk', 'VendorController@bulk')->name('vendors.bulk');

    Route::resource('client_statement', 'ClientStatementController@statement'); // name = (client_statement. index / create / show / update / destroy / edit

    Route::resource('payments', 'PaymentController'); // name = (payments. index / create / show / update / destroy / edit

    Route::post('payments/refund', 'PaymentController@refund')->name('payments.refund');

    Route::post('payments/bulk', 'PaymentController@bulk')->name('payments.bulk');

    Route::post('migrate', 'Migration\MigrateController@index')->name('migrate.start');

//  Route::resource('users', 'UserController')->middleware('password_protected'); // name = (users. index / create / show / update / destroy / edit
    Route::get('users', 'UserController@index');
    Route::put('users/{user}', 'UserController@update')->middleware('password_protected');
    Route::post('users', 'UserController@store')->middleware('password_protected');
    Route::post('users/{user}/attach_to_company', 'UserController@attach')->middleware('password_protected');
    Route::delete('users/{user}/detach_from_company', 'UserController@detach')->middleware('password_protected');


    Route::post('users/bulk', 'UserController@bulk')->name('users.bulk')->middleware('password_protected');

    Route::post('migration/purge/{company}', 'MigrationController@purgeCompany')->middleware('password_protected');
    Route::post('migration/purge_save_settings/{company}', 'MigrationController@purgeCompanySaveSettings')->middleware('password_protected');
    Route::post('migration/start', 'MigrationController@startMigration')->middleware('password_protected');

    Route::resource('companies', 'CompanyController'); // name = (companies. index / create / show / update / destroy / edit

    Route::resource('company_gateways', 'CompanyGatewayController');

    Route::resource('group_settings', 'GroupSettingController');

    Route::resource('tax_rates', 'TaxRateController'); // name = (tasks. index / create / show / update / destroy / edit

    Route::post('refresh', 'Auth\LoginController@refresh');

    Route::post('templates', 'TemplateController@show')->name('templates.show');

    /*
      Route::resource('tasks', 'TaskController'); // name = (tasks. index / create / show / update / destroy / edit

      Route::post('tasks/bulk', 'TaskController@bulk')->name('tasks.bulk');


      Route::resource('credits', 'CreditController'); // name = (credits. index / create / show / update / destroy / edit

      Route::post('credits/bulk', 'CreditController@bulk')->name('credits.bulk');




      Route::get('settings', 'SettingsController@index')->name('user.settings');
    */
    Route::post('support/messages/send', 'Support\Messages\SendingController');
});

Route::fallback('BaseController@notFound');
