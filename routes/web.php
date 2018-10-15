<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', 'HomeController@index')->name('default');
Route::get('/contact/login', 'Auth\ContactLoginController@showLoginForm')->name('contact.login');
Route::post('/contact/login', 'Auth\ContactLoginController@login')->name('contact.login.submit');


Route::group(['middleware' => ['auth:user']], function () {

	Route::get('/home', 'HomeController@user')->name('user.dashboard');
	Route::get('/logout', 'Auth\LoginController@logout')->name('user.logout');
});



Route::group(['prefix' => 'contact',  'middleware' => 'auth:contact'], function () {
   Route::get('/', 'ContactController@index')->name('contact.dashboard');
   Route::get('logout/', 'Auth\ContactLoginController@logout')->name('contact.logout');
}); 