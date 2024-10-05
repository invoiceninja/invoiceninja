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

namespace Tests\Feature\Export;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Report\ProfitLoss;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * 
 *  App\Services\Report\ProfitLoss
 */
class ProfitAndLossReportTest extends TestCase
{
    use MakesHash;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();
    }

    public $company;

    public $user;

    public $payload;

    public $account;

    /**
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
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false,
        ];
    }

    public function testProfitLossInstance()
    {
        $this->buildData();

        $pl = new ProfitLoss($this->company, $this->payload);

        $this->assertInstanceOf(ProfitLoss::class, $pl);

        $this->account->delete();
    }

    public function testExpenseResolution()
    {
        $this->buildData();

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 121,
            'date' => now()->format('Y-m-d'),
            'uses_inclusive_taxes' => true,
            'tax_rate1' => 21,
            'tax_name1' => 'VAT',
            'calculate_tax_by_amount' => false,
            'exchange_rate' => 1,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expense_breakdown = $pl->getExpenseBreakDown();

        $this->assertEquals(100, array_sum(array_column($expense_breakdown, 'total')));
        $this->assertEquals(21, array_sum(array_column($expense_breakdown, 'tax')));

        $this->account->delete();

    }

    public function testMultiCurrencyInvoiceIncome()
    {
        $this->buildData();

        $settings = ClientSettings::defaults();
        $settings->currency_id = 2;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings
        ]);


        $client2 = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'exchange_rate' => 2
        ]);

        Invoice::factory()->create([
            'client_id' => $client2->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'exchange_rate' => 1
        ]);


        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(13.5, $pl->getIncome());
        $this->assertEquals(1.5, $pl->getIncomeTaxes());

        $this->account->delete();

    }

    public function testSimpleInvoiceIncome()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 11,
            'balance' => 11,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(20.0, $pl->getIncome());
        $this->assertEquals(2, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoiceIncomeWithInclusivesTaxes()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(18.0, $pl->getIncome());
        $this->assertEquals(2, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoiceIncomeWithForeignExchange()
    {
        $this->buildData();

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
            'exchange_rate' => 0.5,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(36.0, $pl->getIncome());
        $this->assertEquals(4, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoicePaymentIncome()
    {
        $this->buildData();

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => false,
            'include_tax' => false,
        ];

        $settings = ClientSettings::defaults();
        $settings->currency_id = '1';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
        ]);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 0,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
            'exchange_rate' => 1,
        ]);

        $i->service()->markPaid()->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(10.0, $pl->getIncome());

        $this->account->delete();
    }

    public function testSimpleExpense()
    {
        $this->buildData();

        $e = Expense::factory()->create([
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);

        $this->account->delete();
    }

    public function testSimpleExpenseAmountTax()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->calculate_tax_by_amount = true;
        $e->tax_amount1 = 10;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(10, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseTaxRateExclusive()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->tax_rate1 = 10;
        $e->tax_name1 = 'GST';
        $e->uses_inclusive_taxes = false;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(1, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseTaxRateInclusive()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->tax_rate1 = 10;
        $e->tax_name1 = 'GST';
        $e->uses_inclusive_taxes = false;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(1, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseBreakdown()
    {
        $this->buildData();

        $e = Expense::factory()->create([
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $bd = $pl->getExpenseBreakDown();

        $this->assertEquals(array_sum(array_column($bd, 'total')), 10);

        $this->account->delete();
    }

    public function testSimpleExpenseCategoriesBreakdown()
    {
        $this->buildData();

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Accounting';
        $ec->save();

        $e = Expense::factory()->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Fuel';
        $ec->save();

        $e = Expense::factory(2)->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $bd = $pl->getExpenseBreakDown();

        $this->assertEquals(array_sum(array_column($bd, 'total')), 30);

        $this->account->delete();
    }

    public function testCsvGeneration()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(1)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Accounting';
        $ec->save();

        $e = Expense::factory()->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Fuel';
        $ec->save();

        $e = Expense::factory(2)->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertNotNull($pl->getCsv());

        $this->account->delete();
    }
}
