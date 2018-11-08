<?php
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
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
    Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit
    Route::resource('user', 'UserProfileController'); // name = (clients. index / create / show / update / destroy / edit
    Route::get('settings', 'SettingsController@index')->name('user.settings');



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


Route::get('/js/lang.js', function () {
    $strings = Cache::rememberForever('lang.js', function () {
        $lang = config('app.locale');

        $files   = glob(resource_path('lang/' . $lang . '/*.php'));
        $strings = [];

        foreach ($files as $file) {
            $name           = basename($file, '.php');
            $strings[$name] = require $file;
        }

        return $strings;
    });

    header('Content-Type: text/javascript');
    echo('window.i18n = ' . json_encode($strings) . ';');
    exit();
})->name('assets.lang');


Route::get('/js/hack', function() {
  $lang = config('app.locale');

  $files = glob(resource_path('lang/' . $lang . '/*.php'));

  $strings = [];

        foreach ($files as $file) {
            $name           = basename($file, '.php');
            $strings[$name] = require $file;
        }

        $output = 'window.i18n = ' . json_encode($strings);
dd(public_path('js/'.$lang.'.js'));
        Storage::put(public_path('js/'.$lang.'.js'), $output);
});
/* Dev Playground
Route::get('/mailable', function () {
    $user = App\Models\User::find(1);

    return new App\Mail\VerifyUser($user);
});

*/