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

namespace Tests\Feature\Payments;

use App\DataMapper\ClientSettings;
use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * 
 */
class AutoUnappliedPaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
        // $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUnappliedPaymentsAreEnabled()
    {

        $settings = ClientSettings::defaults();
        $settings->use_unapplied_payment = 'always';

        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'settings' => $settings,
        ]);

        $this->assertEquals('always', $client->settings->use_unapplied_payment);

        $invoice = Invoice::factory()->for($client)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'auto_bill_enabled' => true,
            'client_id' => $client->id,
        ]);

        $invoice  = $invoice->calc()->getInvoice();

        $payment = Payment::factory()->for($client)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'amount' => 100,
            'applied' => 0,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => 0,
        ]);

        $invoice->service()->markSent()->save();

        $this->assertGreaterThan(0, $invoice->balance);

        // nlog($invoice->balance);

        try {
            $invoice->service()->autoBill()->save();
        } catch(\Exception $e) {

        }

        $invoice = $invoice->fresh();
        $payment = $payment->fresh();

        // nlog($invoice->toArray());
        // nlog($payment->toArray());

        $this->assertEquals($payment->applied, $invoice->paid_to_date);
        $this->assertGreaterThan(2, $invoice->status_id);
        $this->assertGreaterThan(0, $payment->applied);

        // $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);
        // $this->assertEquals(0, $invoice->balance);

    }


    public function testUnappliedPaymentsAreDisabled()
    {

        $settings = ClientSettings::defaults();
        $settings->use_unapplied_payment = 'off';

        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'settings' => $settings,
        ]);

        $this->assertEquals('off', $client->settings->use_unapplied_payment);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'auto_bill_enabled' => true,
            'status_id' => 2
        ]);
        $invoice  = $invoice->calc()->getInvoice();
        $invoice_balance = $invoice->balance;

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'amount' => 100,
            'applied' => 0,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED
        ]);

        $invoice->service()->markSent()->save();

        $this->assertGreaterThan(0, $invoice->balance);

        try {
            $invoice->service()->autoBill()->save();
        } catch(\Exception $e) {

        }

        $invoice = $invoice->fresh();
        $payment = $payment->fresh();

        $this->assertEquals($invoice_balance, $invoice->balance);
        $this->assertEquals(0, $payment->applied);
        $this->assertEquals(2, $invoice->status_id);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals($invoice->amount, $invoice->balance);

        // $this->assertEquals($payment->applied, $invoice->paid_to_date);
        // $this->assertEquals(2, $invoice->status_id);


    }

}
