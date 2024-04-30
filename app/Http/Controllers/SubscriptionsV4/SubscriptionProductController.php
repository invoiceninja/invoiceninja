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

namespace App\Http\Controllers\SubscriptionsV4;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class SubscriptionProductController
{
    public function index(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'hello' => 'world',
        ]);
    }
}
