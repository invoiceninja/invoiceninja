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

use App\Http\Controllers\SubscriptionsV4\SubscriptionContextController;

Route::get('/api/v1/subscriptions/{subscription}/v4/context', [SubscriptionContextController::class, 'index']);
Route::post('/api/v1/subscriptions/{subscription}/v4/context', [SubscriptionContextController::class, 'summary']);
