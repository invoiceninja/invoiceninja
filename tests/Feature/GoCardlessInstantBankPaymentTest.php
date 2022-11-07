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
 */
class GoCardlessInstantBankPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private array $mock = [
  'events' => 
  [
    [
      'id' => 'EV032JF',
      'links' => 
      [
        'customer' => 'CU001ZDX',
        'billing_request' => 'BRQ0005',
        'billing_request_flow' => 'BRF0005S6VYV',
        'customer_bank_account' => 'BA001V2111PK6J',
      ],
      'action' => 'payer_details_confirmed',
      'details' => 
      [
        'cause' => 'billing_request_payer_details_confirmed',
        'origin' => 'api',
        'description' => 'Payer has confirmed all their details for this billing request.',
      ],
      'metadata' => [],
      'created_at' => '2022-11-06T08:50:32.641Z',
      'resource_type' => 'billing_requests',
    ],
    [
      'id' => 'EV032JF67TF2',
      'links' => 
      [
        'customer' => 'CU001DXYDR3',
        'billing_request' => 'BRQ005YJ7GHF',
        'customer_bank_account' => 'BA00V2111PK',
        'mandate_request_mandate' => 'MD01W5RP7GA',
      ],
      'action' => 'fulfilled',
      'details' => 
      [
        'cause' => 'billing_request_fulfilled',
        'origin' => 'api',
        'description' => 'This billing request has been fulfilled, and the resources have been created.',
      ],
      'metadata' => [],
      'created_at' => '2022-11-06T08:50:35.134Z',
      'resource_type' => 'billing_requests',
    ],
    [
      'id' => 'EV032JF67S0M8',
      'links' => 
      [
        'mandate' => 'MD001W5RP7GA1W',
      ],
      'action' => 'created',
      'details' => 
      [
        'cause' => 'mandate_created',
        'origin' => 'api',
        'description' => 'Mandate created via the API.',
      ],
      'metadata' => 
      [],
      'created_at' => '2022-11-06T08:50:34.667Z',
      'resource_type' => 'mandates',
    ],
  ],
];


    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testWebhookProcessingWithGoCardless()
    {

      $this->assertIsArray($this->mock);

      foreach($this->mock['events'] as $event)
      {

            if($event['action'] == 'fulfilled' && array_key_exists('billing_request', $event['links'])) {

              $this->assertEquals('CU001DXYDR3', $event['links']['customer']);
              $this->assertEquals('BRQ005YJ7GHF', $event['links']['billing_request']);
              $this->assertEquals('BA00V2111PK', $event['links']['customer_bank_account']);

            }

      }

      // mock the invoice and the payment hash 


    }

}



