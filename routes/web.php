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

Route::get('/', 'GuestController@defaultRoute');

Route::get('/login', ['as' => 'login', 'uses' => 'Auth\LoginController@showLoginForm']);
Route::post('/login', ['as' => 'login', 'uses' => 'Auth\LoginController@login']);
Route::get('/logout', ['as' => 'logout', 'uses' => 'Auth\LoginController@logout']);

Route::group(['middleware' => ['auth:user','verified']], function () {

    Route::get('/dashboard', 'DashboardController@index');


});

require_once app_path() . '/Constants.php';