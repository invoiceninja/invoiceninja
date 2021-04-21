<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use GuzzleHttp\RequestOptions;

/**
 * Class SubscriptionHooker.
 */
trait  SubscriptionHooker
{

	public function sendLoad($subscription, $body)
	{

        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if(count($subscription->webhook_configuration['post_purchase_headers']) >= 1)
        	$headers = array_merge($headers, $subscription->webhook_configuration['post_purchase_headers']);

        $client =  new \GuzzleHttp\Client(
        [
            'headers' => $headers,
        ]);

        try {
            $response = $client->{$subscription->webhook_configuration['post_purchase_rest_method']}($subscription->webhook_configuration['post_purchase_url'],[
                RequestOptions::JSON => ['body' => $body], RequestOptions::ALLOW_REDIRECTS => false
            ]);

            return array_merge($body, ['exception' => json_decode($response->getBody(),true), 'status_code' => $response->getStatusCode()]);
        }
        catch(\Exception $e)
        {
            //;
            // dd($e);
            $body = array_merge($body, ['exception' => ['message' => $e->getMessage(), 'status_code' => 500]]);
            return $body;
        }

	}
}
  