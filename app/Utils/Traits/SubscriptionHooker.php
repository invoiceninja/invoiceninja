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

namespace App\Utils\Traits;

use GuzzleHttp\RequestOptions;

/**
 * Class SubscriptionHooker.
 */
trait SubscriptionHooker
{
    public function sendLoad($subscription, $body)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (count($subscription->webhook_configuration['post_purchase_headers']) >= 1) {
            $headers = array_merge($headers, $subscription->webhook_configuration['post_purchase_headers']);
        }

        $client = new \GuzzleHttp\Client(
        [
            'headers' => $headers,
        ]);

        nlog('method name must be a string');
        nlog($subscription->webhook_configuration['post_purchase_rest_method']);
        nlog($subscription->webhook_configuration['post_purchase_url']);

        $post_purchase_rest_method = (string) $subscription->webhook_configuration['post_purchase_rest_method'];
        $post_purchase_url = (string) $subscription->webhook_configuration['post_purchase_url'];

        try {
            $response = $client->{$post_purchase_rest_method}($post_purchase_url, [
                RequestOptions::JSON => ['body' => $body], RequestOptions::ALLOW_REDIRECTS => false,
            ]);

            return array_merge($body, json_decode($response->getBody(), true));
        } catch (\Exception $e) {
            return array_merge($body, ['message' => $e->getMessage(), 'status_code' => 500]);
        }
    }
}
