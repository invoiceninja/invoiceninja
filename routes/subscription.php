<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionsV4\AuthController;
use App\Http\Controllers\SubscriptionsV4\StripeController;
use App\Http\Controllers\SubscriptionsV4\SubscriptionContextController;

Route::get('/api/v1/subscriptions/{subscription}/v4/context', [SubscriptionContextController::class, 'index']);
Route::post('/api/v1/subscriptions/{subscription}/v4/context', [SubscriptionContextController::class, 'summary']);
Route::post('/api/v1/subscriptions/{subscription}/v4/stripe/intent', [StripeController::class, 'intent']);
Route::post('/api/v1/subscriptions/{subscription}/v4/stripe/charge', [StripeController::class, 'charge']);
Route::post('/api/v1/subscriptions/{subscription}/v4/login/check', [AuthController::class, 'check']);
Route::post('/api/v1/subscriptions/{subscription}/v4/login', [AuthController::class, 'authenticate']);
