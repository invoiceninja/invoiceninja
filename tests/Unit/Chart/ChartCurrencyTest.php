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

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Currency;
use Tests\MockAccountData;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Expense;
use App\Services\Chart\ChartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers  App\Services\Chart\ChartService
 */
class ChartCurrencyTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAggregateRevenues()
    {

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = '';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '1'; //USD

        $usd = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);

        Currency::query()->where('id', 1)->update(['exchange_rate' => 1]);
        Currency::query()->where('id', 2)->update(['exchange_rate' => 0.5]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2'; //GBP

        $gbp = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);


        $i1 = Invoice::factory()->create([
            'client_id' => $usd->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'amount' => 100,
            'balance' => 100,
            'paid_to_date' => 0,
            'status_id' => 2,
            'date' => now(),
            'due_date' => now()
        ]);

        $i2 = Invoice::factory()->create([
            'client_id' => $gbp->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'amount' => 100,
            'balance' => 100,
            'paid_to_date' => 0,
            'status_id' => 2,
            'date' => now(),
            'due_date' => now()
        ]);

        $i1->service()->markPaid()->save();
        $i2->service()->markPaid()->save();

        $cs = new ChartService($company, $this->user, true);
        $results = $cs->totals('1970-01-01', '2050-01-01');

        $this->assertCount(2, $results['currencies']);

        // nlog($results);

        $this->assertEquals('USD', $results['currencies'][1]);
        $this->assertEquals('GBP', $results['currencies'][2]);

        $this->assertEquals(100, $results[1]['invoices']->invoiced_amount);
        $this->assertEquals(100, $results[2]['invoices']->invoiced_amount);

        $this->assertEquals(150, $results[999]['invoices']->invoiced_amount);
        $this->assertEquals(150, $results[999]['revenue']->paid_to_date);

        $usd->forceDelete();
        $gbp->forceDelete();
    }



    public function testAggregateOutstanding()
    {

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = '';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '1'; //USD

        $usd = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);

        Currency::query()->where('id', 1)->update(['exchange_rate' => 1]);
        Currency::query()->where('id', 2)->update(['exchange_rate' => 0.5]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2'; //GBP

        $gbp = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);


        $i1 = Invoice::factory()->create([
            'client_id' => $usd->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'amount' => 100,
            'balance' => 100,
            'paid_to_date' => 0,
            'status_id' => 2,
            'date' => now(),
            'due_date' => now()
        ]);


        $i1_overdue = Invoice::factory()->create([
            'client_id' => $usd->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'amount' => 100,
            'balance' => 100,
            'paid_to_date' => 0,
            'status_id' => 2,
            'date' => now(),
            'due_date' => now()->subDays(10)
        ]);


        $i2 = Invoice::factory()->create([
            'client_id' => $gbp->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'amount' => 100,
            'balance' => 100,
            'paid_to_date' => 0,
            'status_id' => 2,
            'date' => now(),
            'due_date' => now()
        ]);


        $i2_overdue = Invoice::factory()->create([
           'client_id' => $gbp->id,
           'user_id' => $this->user->id,
           'company_id' => $company->id,
           'amount' => 100,
           'balance' => 100,
           'paid_to_date' => 0,
           'status_id' => 2,
           'date' => now(),
           'due_date' => now()->subDays(10)
       ]);

        $i1->service()->markPaid()->save();
        $i2->service()->markPaid()->save();

        $cs = new ChartService($company, $this->user, true);
        $results = $cs->totals('1970-01-01', '2050-01-01');

        $this->assertCount(2, $results['currencies']);

        // nlog($results);

        $this->assertEquals('USD', $results['currencies'][1]);
        $this->assertEquals('GBP', $results['currencies'][2]);

        $this->assertEquals(200, $results[1]['invoices']->invoiced_amount);
        $this->assertEquals(200, $results[2]['invoices']->invoiced_amount);

        $this->assertEquals(300, $results[999]['invoices']->invoiced_amount);
        $this->assertEquals(150, $results[999]['revenue']->paid_to_date);

        $this->assertEquals(150, $results[999]['outstanding']->amount);
        $this->assertEquals(2, $results[999]['outstanding']->outstanding_count);

        $usd->forceDelete();
        $gbp->forceDelete();
    }




    public function testAggregateExpenses()
    {

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = '';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '1'; //USD

        $usd = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);

        Currency::query()->where('id', 1)->update(['exchange_rate' => 1]);
        Currency::query()->where('id', 2)->update(['exchange_rate' => 0.5]);

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2'; //GBP

        $gbp = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'settings' => $settings,
        ]);

        $usd_e = Expense::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $usd->id,
            'amount' => 100,
        ]);

        $gbp_e = Expense::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $usd->id,
            'amount' => 100,
        ]);


        $cs = new ChartService($company, $this->user, true);
        $results = $cs->totals('1970-01-01', '2050-01-01');

        $this->assertCount(2, $results['currencies']);

        // nlog($results);

        // $this->assertEquals('USD', $results['currencies'][1]);
        // $this->assertEquals('GBP', $results['currencies'][2]);

        // $this->assertEquals(200, $results[1]['invoices']->invoiced_amount);
        // $this->assertEquals(200, $results[2]['invoices']->invoiced_amount);

        // $this->assertEquals(300, $results[999]['invoices']->invoiced_amount);
        // $this->assertEquals(150, $results[999]['revenue']->paid_to_date);

        // $this->assertEquals(150, $results[999]['outstanding']->amount);
        // $this->assertEquals(2, $results[999]['outstanding']->outstanding_count);

        $usd->forceDelete();
        $gbp->forceDelete();
    }



    public function testRevenueValues()
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'balance' => 0,
            'paid_to_date' => 100,
            'status_id' => 4,
            'date' => now(),
            'due_date' => now(),
            'number' => 'db_record',
        ]);

        $this->assertDatabaseHas('invoices', ['number' => 'db_record']);

        $cs = new ChartService($this->company, $this->user, true);
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

        $cs = new ChartService($this->company, $this->user, true);

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

        $cs = new ChartService($this->company, $this->user, true);

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
