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

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Jobs\Util\ReminderJob;
use App\DataMapper\InvoiceItem;
use App\DataMapper\FeesAndLimits;
use App\DataMapper\CompanySettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 */
class LateFeeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    public $account;

    public $company;

    public $client;

    protected function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();

    }

    private function buildData($settings)
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

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $settings = new \stdClass;
        $settings->currency_id = '1';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        return $client;
    }

    public function testLateFeeAdded()
    {

        $this->travelTo(now()->subDays(15));

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;
        $settings->late_fee_amount1 = 10;
        $settings->late_fee_percent1 = 0;
        $settings->lock_invoices = 'off';
        $settings->enable_reminder1 = true;
        $settings->num_days_reminder1 = 10;
        $settings->schedule_reminder1 = 'after_due_date';
        $settings->entity_send_time = '0';

        $client = $this->buildData($settings);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->markSent()->setReminder()->applyNumber()->createInvitations()->save();

        // $this->travelBack();
        $this->travelTo(now()->addDays(20)->startOfDay()->format('Y-m-d'));
        $i = $i->fresh();

        $this->assertEquals(10, $i->amount);
        $this->assertEquals(10, $i->balance);
        
        $reflectionMethod = new \ReflectionMethod(ReminderJob::class, 'sendReminderForInvoice');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs(new ReminderJob(), [$i]);

        $i->fresh();

        $this->assertEquals(20, $i->balance);

        $this->travelBack();

    }

    public function testLateFeeAddedToNewInvoiceWithLockedInvoiceConfig()
    {

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;
        $settings->late_fee_amount1 = 10;
        $settings->late_fee_percent1 = 0;
        $settings->lock_invoices = 'when_sent';
        $settings->enable_reminder1 = true;
        $settings->num_days_reminder1 = 10;
        $settings->schedule_reminder1 = 'after_due_date';

        $client = $this->buildData($settings);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->applyNumber()->createInvitations()->markSent()->save();
        
        $this->assertEquals(10, $i->amount);
        $this->assertEquals(10, $i->balance);
        $this->assertEquals(10, $client->fresh()->balance);

        $reflectionMethod = new \ReflectionMethod(ReminderJob::class, 'sendReminderForInvoice');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs(new ReminderJob(), [$i]);

        $i->fresh();

        $this->assertEquals(10, $i->balance);
        $this->assertEquals(20, $client->fresh()->balance);
    }


    public function testLateFeeBalances()
    {
        $this->assertEquals(10, $this->client->balance);
        $this->assertEquals(10, $this->invoice->balance);

        $this->invoice = $this->setLateFee($this->invoice, 5, 0);

        $this->assertEquals(15, $this->client->fresh()->balance);
        $this->assertEquals(15, $this->invoice->fresh()->balance);
    }

    private function setLateFee($invoice, $amount, $percent) :Invoice
    {
        $temp_invoice_balance = $invoice->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $invoice;
        }

        $fee = $amount;

        if ($invoice->partial > 0) {
            $fee += round($invoice->partial * $percent / 100, 2);
        } else {
            $fee += round($invoice->balance * $percent / 100, 2);
        }

        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => now()]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice = $invoice->calc()->getInvoice();

        $invoice->client->service()->updateBalance($invoice->balance - $temp_invoice_balance)->save();
        $invoice->ledger()->updateInvoiceBalance($invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$invoice->number}");

        return $invoice;
    }
}
