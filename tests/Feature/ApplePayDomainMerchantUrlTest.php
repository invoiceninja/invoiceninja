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

namespace Tests\Feature;

use App\Models\CompanyGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ApplePayDomainController
 */
class ApplePayDomainMerchantUrlTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testMerchantFieldGet()
    {
        // if (! config('ninja.testvars.stripe')) {
        $this->markTestSkipped('Skip test no company gateways installed');
        // }

        $config = new \stdClass();
        $config->publishableKey = 'pk_test';
        $config->apiKey = 'sk_test';
        $config->appleDomainVerification = 'merchant_id';

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->setConfig($config);
        $cg->fees_and_limits = '';
        $cg->save();

        $response = $this->withHeaders([])->get('.well-known/apple-developer-merchantid-domain-association');

        $arr = $response->getContent();
        $response->assertStatus(200);
        $this->assertEquals('merchant_id', $arr);
    }

    public function testDomainParsing()
    {
        $domain = 'http://ninja.test:8000';

        $parsed = parse_url($domain);

        $this->assertEquals('ninja.test', $parsed['host']);

        $domain = 'ninja.test:8000';

        $parsed = parse_url($domain);

        $this->assertEquals('ninja.test', $parsed['host']);

        $domain = 'http://ninja.test:8000/afadf/dfdfdf/dfdfasf';

        $parsed = parse_url($domain);

        $this->assertEquals('ninja.test', $parsed['host']);
    }
}
