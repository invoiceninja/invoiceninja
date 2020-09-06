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
*/

Route::group(['middleware' => ['api_secret_check']], function () {
    Route::post('api/v1/contact/login', 'Contact\LoginController@apiLogin');
});

Route::group(['middleware' => ['contact_db', 'api_secret_check', 'contact_token_auth'], 'prefix' =>'api/v1/contact', 'as' => 'api.contact.'], function () {
    Route::get('invoices', 'Contact\InvoiceController@index'); // name = (clients. index / create / show / update / destroy / edit
});
