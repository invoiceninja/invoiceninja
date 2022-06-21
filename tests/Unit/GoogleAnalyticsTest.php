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

use App\Utils\Ninja;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Listeners\Payment\PaymentNotification
 */
class GoogleAnalyticsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testGoogleAnalyticsLogic()
    {
        $this->withoutEvents();

        $analytics_id = 'analytics_id';
        $invoice = $this->invoice;
        $client = $this->client;

        $invoice->service()->markPaid()->save();

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
}
