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

namespace Tests\Unit\Tax;

use App\DataProviders\USStates;
use App\Models\Client;
use App\Services\Tax\Providers\TaxProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test App\Services\Tax\Providers\EuTax
 */
class TaxConfigTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();

        if(!config('services.tax.zip_tax.key')) {
            $this->markTestSkipped('No API keys to test with.');
        }
    }

    public TaxProvider $tp;

    private function bootApi(Client $client)
    {
        $this->tp = new TaxProvider($this->company, $client);
    }

    public function testStateResolution()
    {
        //infer state from zip
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'address1' => '400 Evelyn Pl',
            'city' => 'Beverley Hills',
            'state' => '',
            'postal_code' => '',
            'country_id' => 840,
        ]);


        // $this->assertEquals('CA', USStates::getState('90210'));

        $this->bootApi($client);

        $this->tp->updateClientTaxData();

    }

}
