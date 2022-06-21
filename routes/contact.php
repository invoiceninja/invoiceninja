<?php

use App\Http\Controllers\Contact;
use Illuminate\Support\Facades\Route;

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

Route::middleware('api_secret_check')->group(function () {
    Route::post('api/v1/contact/login', [Contact\LoginController::class, 'apiLogin']);
});

Route::middleware('contact_db', 'api_secret_check', 'contact_token_auth')->prefix('api/v1/contact')->name('api.contact.')->group(function () {
    Route::get('invoices', [Contact\InvoiceController::class, 'index']); // name = (clients. index / create / show / update / destroy / edit
});
