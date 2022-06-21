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

namespace Tests\Unit\Chart;

use App\DataMapper\ClientSettings;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Chart\ChartService;
use App\Utils\Ninja;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Services\Chart\ChartService
 */
class ChartCurrencyTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testRevenueValues()
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'paid_to_date' => 100,
            'status_id' => 4,
            'date' => now(),
            'due_date'=> now(),
            'number' => 'db_record',
        ]);

        $this->assertDatabaseHas('invoices', ['number' => 'db_record']);

        $cs = new ChartService($this->company);
        // nlog($cs->getRevenueQuery(now()->subDays(20)->format('Y-m-d'), now()->addDays(100)->format('Y-m-d')));

        $data = [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->addDays(100)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/totals', $data);

        $response->assertStatus(200);
    }

    public function testgetCurrencyCodes()
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = '1'; //USD

        Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2'; //GBP

        Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $cs = new ChartService($this->company);

        $this->assertTrue(is_array($cs->getCurrencyCodes()));

        $this->assertTrue(in_array('GBP', $cs->getCurrencyCodes()));
        $this->assertTrue(in_array('USD', $cs->getCurrencyCodes()));
        $this->assertFalse(in_array('AUD', $cs->getCurrencyCodes()));
    }

    public function testGetChartTotalsApi()
    {
        $data = [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/totals', $data);

        $response->assertStatus(200);
    }

    public function testClientServiceDataSetBuild()
    {
        $haystack = [
            [
                'currency_id' => null,
                'amount' => 10,
            ],
            [
                'currency_id' => 1,
                'amount' => 11,
            ],
            [
                'currency_id' => 2,
                'amount' => 12,
            ],
            [
                'currency_id' => 3,
                'amount' => 13,
            ],
        ];

        $cs = new ChartService($this->company);

        // nlog($cs->totals(now()->subYears(10), now()));

        $this->assertTrue(is_array($cs->totals(now()->subYears(10), now())));
    }

    /* coalesces the company currency with the null currencies */
    public function testFindNullValueinArray()
    {
        $haystack = [
            [
                'currency_id' => null,
                'amount' => 10,
            ],
            [
                'currency_id' => 1,
                'amount' => 11,
            ],
            [
                'currency_id' => 2,
                'amount' => 12,
            ],
            [
                'currency_id' => 3,
                'amount' => 13,
            ],
        ];

        $company_currency_id = 1;

        $c_key = array_search($company_currency_id, array_column($haystack, 'currency_id'));

        $this->assertNotEquals($c_key, 2);
        $this->assertEquals($c_key, 1);

        $key = array_search(null, array_column($haystack, 'currency_id'));

        $this->assertNotEquals($key, 39);
        $this->assertEquals($key, 0);

        $null_currency_amount = $haystack[$key]['amount'];

        unset($haystack[$key]);

        $haystack[$c_key]['amount'] += $null_currency_amount;

        $this->assertEquals($haystack[$c_key]['amount'], 21);
    }

    public function testCollectionMerging()
    {
        $currencies = collect([1, 2, 3, 4, 5, 6]);

        $expense_currencies = collect([4, 5, 6, 7, 8]);

        $currencies = $currencies->merge($expense_currencies);

        $this->assertEquals($currencies->count(), 11);

        $currencies = $currencies->unique();

        $this->assertEquals($currencies->count(), 8);
    }
}
