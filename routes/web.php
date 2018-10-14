<?php

Auth::routes(['verify' => true]);

Route::get('/', 'GuestController@defaultRoute');
Route::get('/home', 'HomeController@index')->name('home');

Route::get('/login/user', 'Auth\LoginController@showLoginForm');
Route::get('/login/contact', 'Auth\LoginController@showLoginForm');
Route::get('/register/user', 'Auth\RegisterController@showUserRegisterForm');
Route::get('/register/contact', 'Auth\RegisterController@showContactRegisterForm');

Route::post('/login/user', 'Auth\LoginController@userLogin');
Route::post('/login/contact', 'Auth\LoginController@contactLogin');
Route::post('/register/user', 'Auth\RegisterController@createUser');
Route::post('/register/contact', 'Auth\RegisterController@createContact');

Route::view('/home', 'home')->middleware('auth');
Route::view('/admin', 'admin');
Route::view('/writer', 'writer');

Route::group(['middleware' => ['auth:user']], function () {

    Route::get('/dashboard', 'DashboardController@index');
	Route::get('/logout', ['as' => 'logout', 'uses' => 'Auth\LoginController@logout']);


});

