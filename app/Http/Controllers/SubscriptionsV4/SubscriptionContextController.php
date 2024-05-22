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
use App\Utils\Statics;
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

        $stripe = $subscription->company->company_gateways
            ->where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')
            ->first();

        $registration_fields = collect($subscription->company->client_registration_fields)->map(function ($field) use ($subscription) {
            if (in_array($field['key'], ['custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'])) {
                $translation = (new \App\Utils\Helpers())->makeCustomField($subscription->company->custom_fields, str_replace("custom_value", "client", $field['key']));
            } elseif (array_key_exists('label', $field)) {
                $translation = ctrans("texts.{$field['label']}");
            } else {
                $translation = ctrans("texts.{$field['key']}");
            }

            if ($translation === '') {
                $translation = ctrans("texts.{$field['key']}");
            }

            $field['translation'] = $translation;

            return $field;
        });

        $statics = Statics::company();

        return response()->json([
            'context' => [
                'per_seat_enabled' => $subscription->per_seat_enabled,
                'min_seats_limit' => $subscription->min_seats_limit,
                'max_seats_limit' => $subscription->max_seats_limit,
                'registration_fields' => $registration_fields,
                'statics' => $statics,
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
            'gateways' => [
                [
                    'id' => $stripe->hashed_id,
                    'key' => 'd14dd26a37cecc30fdd65700bfb55b23',
                    'fields' => $stripe->driver()->getClientRequiredFields(),
                    'public_key' => $stripe->driver()->getPublishableKey(),
                ],
            ],
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
