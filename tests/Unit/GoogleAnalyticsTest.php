<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Listeners\Payment\PaymentNotification;
use App\Utils\Ninja;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Listeners\Payment\PaymentNotification
 */
class GoogleAnalyticsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testGoogleAnalyticsLogic()
    {

        $analytics_id = 'analytics_id';
        $invoice = $this->invoice;
        $client = $this->client;

        $invoice = $invoice->service()->markPaid()->save();

        $payment = $invoice->payments->first();

        $amount = $payment->amount;

        if ($invoice) {
            $items = $invoice->line_items;
            $item = end($items)->product_key;
            $entity_number = $invoice->number;
        } else {
            $item = $payment->number;
            $entity_number = $item;
        }

        $currency_code = $client->getCurrencyCode();

        if (Ninja::isHosted()) {
            $item .= ' [R]';
        }

        $base = "v=1&tid={$analytics_id}&cid={$client->id}&cu={$currency_code}&ti={$entity_number}";

        $url = $base."&t=transaction&ta=ninja&tr={$amount}";

        $url = $base."&t=item&in={$item}&ip={$amount}&iq=1";

        $this->assertNotNull($url);
    }

    public function testSendAnalyticsPostsMeasurementProtocolPayload(): void
    {
        Http::fake();

        $hit = 'v=1&tid=UA-123&cid=1&cu=USD&ti=0001&t=transaction&ta=ninja&tr=10';

        $method = new ReflectionMethod(PaymentNotification::class, 'sendAnalytics');
        $method->invoke(new PaymentNotification(), $hit);

        Http::assertSent(function ($request) use ($hit) {
            return $request->url() === config('ninja.google_analytics_url')
                && $request->method() === 'POST'
                && $request->body() === mb_convert_encoding($hit, 'UTF-8')
                && $request->header('Content-Type')[0] === 'application/x-www-form-urlencoded';
        });
    }
}
