<?php

Route::get('client/login', 'Auth\ContactLoginController@showLoginForm')->name('client.login');
Route::post('client/login', 'Auth\ContactLoginController@login')->name('client.login.submit');

Route::get('client/password/reset', 'Auth\ContactForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('client/password/email', 'Auth\ContactForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('client/password/reset/{token}', 'Auth\ContactResetPasswordController@showResetForm')->name('password.reset');
Route::post('client/password/reset', 'Auth\ContactResetPasswordController@reset')->name('password.update');

//todo implement domain DB
//Route::group(['middleware' => ['auth:contact', 'domain_db'], 'prefix' => 'client', 'as' => 'client.'], function () {
Route::group(['middleware' => ['auth:contact'], 'prefix' => 'client', 'as' => 'client.'], function () {

	Route::get('dashboard', 'ClientPortal\DashboardController@index')->name('dashboard'); // name = (dashboard. index / create / show / update / destroy / edit

	Route::get('logout', 'Auth\ContactLoginController@logout')->name('logout');

});

Route::fallback('BaseController@notFoundClient');