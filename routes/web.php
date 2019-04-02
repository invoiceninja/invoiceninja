<?php
/*
 * Authentication Routes Laravel Defaults... replaces //Auth::routes();
 */

Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');

/*
 * Signup Routes
 */

Route::redirect('/', '/login', 301);
Route::get('signup', 'AccountController@index')->name('signup');
Route::post('signup', 'AccountController@store')->name('signup.submit');
Route::get('contact/login', 'Auth\ContactLoginController@showLoginForm')->name('contact.login');
Route::post('contact/login', 'Auth\ContactLoginController@login')->name('contact.login.submit');

/*
 *  Password Reset Routes...
 */

Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.update');

/*
 * Social authentication
 */

Route::get('auth/{provider}', 'Auth\LoginController@redirectToProvider');
Route::get('auth/{provider}/callback', 'Auth\LoginController@handleProviderCallback');

/*
 * Authenticated User Routes
 */

Route::group(['middleware' => ['auth:user', 'db']], function () {

	Route::resource('dashboard', 'DashboardController'); // name = (dashboard. index / create / show / update / destroy / edit

	Route::get('logout', 'Auth\LoginController@logout')->name('user.logout');

  Route::resource('invoices', 'InvoiceController'); // name = (invoices. index / create / show / update / destroy / edit

  Route::post('invoices/bulk', 'InvoiceController@bulk')->name('invoices.bulk');

  Route::resource('quotes', 'QuoteController'); // name = (quotes. index / create / show / update / destroy / edit

  Route::post('quotes/bulk', 'QuoteController@bulk')->name('quotes.bulk');

  Route::resource('recurring_invoices', 'RecurringInvoiceController'); // name = (recurring_invoices. index / create / show / update / destroy / edit

  Route::post('recurring_invoices/bulk', 'RecurringInvoiceController@bulk')->name('recurring_invoices.bulk');

  Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit

  Route::post('clients/bulk', 'ClientController@bulk')->name('clients.bulk');

  Route::resource('client_statement', 'ClientStatementController@statement'); // name = (client_statement. index / create / show / update / destroy / edit
/*
  Route::resource('tasks', 'TaskController'); // name = (tasks. index / create / show / update / destroy / edit
  
  Route::post('tasks/bulk', 'TaskController@bulk')->name('tasks.bulk');
  
  Route::resource('payments', 'PaymentController'); // name = (payments. index / create / show / update / destroy / edit
  
  Route::post('payments/bulk', 'PaymentController@bulk')->name('payments.bulk');
  
  Route::resource('credits', 'CreditController'); // name = (credits. index / create / show / update / destroy / edit
  
  Route::post('credits/bulk', 'CreditController@bulk')->name('credits.bulk');
  
  Route::resource('expenses', 'ExpenseController'); // name = (expenses. index / create / show / update / destroy / edit
  
  Route::post('expenses/bulk', 'ExpenseController@bulk')->name('expenses.bulk');
  
  Route::resource('user', 'UserProfileController'); // name = (clients. index / create / show / update / destroy / edit
  
  Route::get('settings', 'SettingsController@index')->name('user.settings');
*/


});

/*
 * Inbound routes requiring DB Lookup
 */
Route::group(['middleware' => ['url-db']], function () {

    Route::get('/user/confirm/{confirmation_code}', 'UserController@confirm');

});

/*
Authenticated Contact Routes
 */

Route::group(['prefix' => 'contact',  'middleware' => 'auth:contact'], function () {

   Route::get('/', 'ContactController@index')->name('contact.dashboard');
   Route::get('logout', 'Auth\ContactLoginController@logout')->name('contact.logout');
   
});

/*
 * Injects users translation strings in json format for frontend consumption.
 */
Route::get('js/lang.js', 'TranslationController@index')->name('assets.lang');



/* Dev Playground
Route::get('/mailable', function () {
    $user = App\Models\User::find(1);

    return new App\Mail\VerifyUser($user);
});

*/