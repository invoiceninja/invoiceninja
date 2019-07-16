<?php

Route::get('client/login', 'Auth\ContactLoginController@showLoginForm')->name('client.login');
Route::post('client/login', 'Auth\ContactLoginController@login')->name('client.login.submit');

//todo implement domain DB
//Route::group(['middleware' => ['auth:contact', 'domain_db'], 'prefix' => 'client', 'as' => 'client.'], function () {
Route::group(['middleware' => ['auth:contact'], 'prefix' => 'client', 'as' => 'client.'], function () {

	Route::get('dashboard', 'ClientPortal\DashboardController@index')->name('dashboard'); // name = (dashboard. index / create / show / update / destroy / edit

	Route::get('logout', 'Auth\ContactLoginController@logout')->name('logout');

});