<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['company_key_db','locale'], 'prefix' => 'api/v1'], function () {

	Route::get('shop/products', 'Shop\ProductController@index');
	Route::get('shop/clients', 'Shop\ClientController@index');
	Route::get('shop/invoices', 'Shop\InvoiceController@index');
	Route::get('shop/client/{contact_key}', 'Shop\ClientController@show');
	Route::get('shop/invoice/{invitation_key}', 'Shop\InvoiceController@show');
	Route::get('shop/product/{product_key}', 'Shop\ProductController@show');

});