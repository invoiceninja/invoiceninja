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
namespace Tests\Feature\Export;

use App\Factory\InvoiceFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Report\ProfitLoss;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Report\ProfitLoss
 */
class ProfitAndLossReportTest extends TestCase
{
    use MakesHash;

    public $faker;

    public function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->buildData();
    }

    public $company;

    public $user;

    public $payload;

/**
 *
 *      start_date - Y-m-d
        end_date - Y-m-d
        date_range - 
            all
            last7
            last30
            this_month
            last_month
            this_quarter
            last_quarter
            this_year
            custom
        is_income_billed - true = Invoiced || false = Payments
        expense_billed - true = Expensed || false = Expenses marked as paid
        include_tax - true tax_included || false - tax_excluded

*/

    private function buildData()
    {


        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000
        ]);
        
        $account->num_users = 3;
        $account->save();
        
        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail,
        ]);

        $this->company = Company::factory()->create([
                'account_id' => $account->id,
            ]);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false
        ];

    }

    public function testProfitLossInstance()
    {
 
        $pl = new ProfitLoss($this->company, $this->payload);

        $this->assertInstanceOf(ProfitLoss::class, $pl);

    }

    public function testInvoiceIncome()
    {

        $client = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'is_deleted' => 0,
            ]);

        // Invoice::factory()->create([
        //     'client_id' => $client->id,
        //     'user_id' => $this->user->id,
        //     'company_id' => $this->company->id,
        //     'amount' => 10,
        //     'balance' => 10,
        //     'status_id' => 2,
        //     'total_taxes' => 1,
        //     'date' => '2022-01-01',
        //     'terms' => 'nada',
        //     'discount' => 0,
        //     'tax_rate1' => 0,
        //     'tax_rate2' => 0,
        //     'tax_rate3' => 0,
        //     'tax_name1' => '',
        //     'tax_name2' => '',
        //     'tax_name3' => '',
        // ]);

        $i = InvoiceFactory::create($this->company->id, $this->user->id);
        $i->client_id = $client->id;
        $i->amount = 10;
        $i->balance = 10;
        $i->status_id = 2;
        $i->terms = "nada";
        $i->total_taxes = 1;
        $i->save();

        nlog(Invoice::where('company_id', $this->company->id)->get()->toArray());

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();


        $this->assertEquals(9.0, $pl->getIncome());
        $this->assertEquals(1, $pl->getIncomeTaxes());


    }
}
