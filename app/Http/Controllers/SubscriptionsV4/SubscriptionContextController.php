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
use App\Services\SubscriptionV4\SummaryService;
use Illuminate\Http\JsonResponse;

class SubscriptionContextController
{
    public function index(Subscription $subscription): JsonResponse
    {
        $bundle = [
            'recurring_products' => $subscription->service()->recurring_products()->map(fn($product) => [
                'id' => $product->hashed_id,
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'price' => $product->price,
                'product_image' => $product->product_image,
                'in_stock_quantity' => $product->in_stock_quantity,
                'bundle' => [
                    'quantity' => 1,
                ],
            ]),
            'products' => $subscription->service()->products()->map(fn($product) => [
                'id' => $product->hashed_id,
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'price' => $product->price,
                'product_image' => $product->product_image,
                'in_stock_quantity' => $product->in_stock_quantity,
                'bundle' => [
                    'quantity' => 1,
                ],
            ]),
            'optional_recurring_products' => $subscription->service()->optional_recurring_products()->map(fn($product) => [
                'id' => $product->hashed_id,
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'price' => $product->price,
                'product_image' => $product->product_image,
                'in_stock_quantity' => $product->in_stock_quantity,
                'bundle' => [
                    'quantity' => 0,
                ],
            ]),
            'optional_products' => $subscription->service()->optional_products()->map(fn($product) => [
                'id' => $product->hashed_id,
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'price' => $product->price,
                'product_image' => $product->product_image,
                'in_stock_quantity' => $product->in_stock_quantity,
                'bundle' => [
                    'quantity' => 0,
                ],
            ]),
        ];

        $service = new SummaryService($bundle);

        return response()->json([
            'context' => [
                'per_seat_enabled' => $subscription->per_seat_enabled,
                'min_seats_limit' => $subscription->min_seats_limit,
                'max_seats_limit' => $subscription->max_seats_limit,
            ],
            'summary' => [
                'one_time_total' => $service->oneTimePurchasesTotal(),
                'recurring_total' => $service->recurringPurchasesTotal(),
                'total' => $service->oneTimePurchasesTotal() + $service->recurringPurchasesTotal(),
            ],
            'recurring_products' => $bundle['recurring_products'],
            'products' => $bundle['products'],
            'optional_recurring_products' => $bundle['optional_recurring_products'],
            'optional_products' => $bundle['optional_products'],
        ]);
    }

    public function summary(Subscription $subscription): JsonResponse
    {
        $context = request()->all();
        $service = new SummaryService($context);

        $context['summary'] = [
            'one_time_total' => $service->oneTimePurchasesTotal(),
            'recurring_total' => $service->recurringPurchasesTotal(),
            'total' => $service->oneTimePurchasesTotal() + $service->recurringPurchasesTotal(),
        ];

        return response()->json($context);
    }
}
