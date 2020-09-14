<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Models\Client
 */
class ClientModelTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for Travis');
        }

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    public function testPaymentMethods()
    {
        $amount = 40;

        $company_gateways = $this->client->getSetting('company_gateway_ids');

        //todo create a test where we actually SET a value in the settings->company_gateways object and test if we can harvest.

        if ($company_gateways) {
            $gateways = $this->company->company_gateways->whereIn('id', $payment_gateways);
        } else {
            $gateways = $this->company->company_gateways;
        }

        $this->assertNotNull($gateways);

        $pre_count = $gateways->count();

        $gateways->filter(function ($method) use ($amount) {
            if ($method->min_limit !== null && $amount < $method->min_limit) {
                return false;
            }

            if ($method->max_limit !== null && $amount > $method->min_limit) {
                return false;
            }
        });

        $post_count = $gateways->count();

        $this->assertEquals($pre_count, $post_count);

        $payment_methods = [];

        foreach ($gateways as $gateway) {
            foreach ($gateway->driver($this->client)->gatewayTypes() as $type) {
                $payment_methods[] = [$gateway->id => $type];
            }
        }

        $this->assertEquals(8, count($payment_methods));

        $payment_methods_collections = collect($payment_methods);

        //** Plucks the remaining keys into its own collection
        $payment_methods_intersect = $payment_methods_collections->intersectByKeys($payment_methods_collections->flatten(1)->unique());

        $this->assertEquals(4, $payment_methods_intersect->count());

        $payment_urls = [];

        foreach ($payment_methods_intersect as $key => $child_array) {
            foreach ($child_array as $gateway_id => $gateway_type_id) {
                $gateway = $gateways->where('id', $gateway_id)->first();

                $this->assertNotNull($gateway);

                $fee_label = $gateway->calcGatewayFeeLabel($amount, $this->client);

                $payment_urls[] = [
                'label' => ctrans('texts.'.$gateway->getTypeAlias($gateway_type_id)).$fee_label,
                'url'   =>  URL::signedRoute('client.payments.process', [
                                            'company_gateway_id' => $gateway_id,
                                            'gateway_type_id' => $gateway_type_id, ]),
                            ];
            }
        }

        $this->assertEquals(4, count($payment_urls));
    }
}
