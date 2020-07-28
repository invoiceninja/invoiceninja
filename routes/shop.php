<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['api_db','shop_token_auth','locale']], function () {

	Route::get('products', 'Shop\ProductController@index');
	Route::get('clients', 'Shop\ClientController@index');
	Route::get('invoices', 'Shop\InvoiceController@index');
	Route::get('client/{contact_key}', 'Shop\ClientController@show');
	Route::get('invoice/{invitation_key}', 'Shop\InvoiceController@show');
	Route::get('product/{product_key}', 'Shop\ProductController@show');

});