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

use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Tests\TestCase;

/**
 * 
 */
class EntityTranslationTest extends TestCase
{
    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();
    }

    public function testTranslations()
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $u = User::factory()->create([
            'email' => $this->faker->email(),
            'account_id' => $account->id,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
        ]);

        $credit = Credit::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
            'number' => 'xxx',
        ]);

        $expense = Expense::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
            'number' => 'xxx',
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
        ]);

        $project = Project::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);

        $quote = Quote::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
            'number' => 'xxx',
        ]);

        $recurring_expense = RecurringExpense::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);

        $recurring_invoice = RecurringInvoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
            'number' => 'xxx',
        ]);

        $recurring_quote = RecurringQuote::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'client_id' => $client->id,
        ]);


        $vendor = Vendor::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'company_id' => $company->id,
            'user_id' => $u->id,
            'vendor_id' => $vendor->id,
            'number' => 'xxx',
        ]);

        $this->assertEquals(ctrans('texts.user'), $u->translate_entity());
        $this->assertEquals(ctrans('texts.company'), $company->translate_entity());
        $this->assertEquals(ctrans('texts.client'), $client->translate_entity());
        $this->assertEquals(ctrans('texts.credit'), $credit->translate_entity());
        $this->assertEquals(ctrans('texts.expense'), $expense->translate_entity());
        $this->assertEquals(ctrans('texts.invoice'), $invoice->translate_entity());
        $this->assertEquals(ctrans('texts.payment'), $payment->translate_entity());
        $this->assertEquals(ctrans('texts.product'), $product->translate_entity());
        $this->assertEquals(ctrans('texts.project'), $project->translate_entity());
        $this->assertEquals(ctrans('texts.quote'), $quote->translate_entity());
        $this->assertEquals(ctrans('texts.recurring_expense'), $recurring_expense->translate_entity());
        $this->assertEquals(ctrans('texts.recurring_invoice'), $recurring_invoice->translate_entity());
        $this->assertEquals(ctrans('texts.recurring_quote'), $recurring_quote->translate_entity());
        $this->assertEquals(ctrans('texts.task'), $task->translate_entity());
        $this->assertEquals(ctrans('texts.vendor'), $vendor->translate_entity());
        $this->assertEquals(ctrans('texts.purchase_order'), $po->translate_entity());

        $this->assertEquals(str_replace(" ", "_", ctrans('texts.purchase_order')) . "_xxx.pdf", $po->getFileName());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.credit')) . "_xxx.pdf", $credit->getFileName());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.invoice')) . "_xxx.pdf", $invoice->getFileName());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.quote')) . "_xxx.pdf", $quote->getFileName());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.recurring_invoice')) . "_xxx.pdf", $recurring_invoice->getFileName());

        $this->assertEquals(str_replace(" ", "_", ctrans('texts.recurring_invoice')) . "_xxx", $recurring_invoice->numberFormatter());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.credit')) . "_xxx", $credit->numberFormatter());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.invoice')) . "_xxx", $invoice->numberFormatter());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.quote')) . "_xxx", $quote->numberFormatter());
        $this->assertEquals(str_replace(" ", "_", ctrans('texts.purchase_order')) . "_xxx", $po->numberFormatter());
    }
}
