<?php

use App\Http\Controllers\Auth\ContactForgotPasswordController;
use App\Http\Controllers\Auth\ContactLoginController;
use App\Http\Controllers\Auth\ContactRegisterController;
use App\Http\Controllers\Auth\ContactResetPasswordController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClientPortal\DocumentController;
use App\Http\Controllers\ClientPortal\PaymentMethodController;
use App\Http\Controllers\ClientPortal\SubscriptionController;
use App\Http\Controllers\ClientPortal\TaskController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Utils\PhantomJS\Phantom;
use Illuminate\Support\Facades\Route;

Route::fallback([BaseController::class, 'notFoundClient']);
