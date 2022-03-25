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
namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Factory\QuoteFactory;
use App\Factory\VendorFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Timezone;
use App\Models\User;
use App\Utils\Traits\GeneratesConvertedQuoteCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesConvertedQuoteCounter
 */
class GeneratesConvertedQuoteCounterTest extends TestCase
{
    use GeneratesConvertedQuoteCounter;
    use DatabaseTransactions;
    use MakesHash;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();
        $this->faker = \Faker\Factory::create();
        Model::reguard();

    }

    public function testCounterExtraction()
    {

        $user = User::whereEmail('user@example.com')->first();

        $user_id = $user->id;

        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000
        ]);
        
        $this->account->num_users = 3;
        $this->account->save();
        
        $this->company = Company::factory()->create([
                'account_id' => $this->account->id,
            ]);

        $this->client = Client::factory()->create([
                'user_id' => $user_id,
                'company_id' => $this->company->id,
        ]);

        $contact = ClientContact::factory()->create([
                'user_id' => $user_id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'send_email' => true,
        ]);

        $settings = $this->client->getMergedSettings();
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-I{$counter}';
        $settings->quote_number_pattern = '{$year}-Q{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $this->company->settings = $settings;

        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id, 
            'company_id' => $this->client->company_id, 
            'client_id' => $this->client->id
        ]);

        $quote = $quote->service()->markSent()->convert()->save();

        $invoice = Invoice::find($quote->invoice_id);

        $this->assertNotNull($invoice);

        $this->assertEquals('2022-Q0001', $quote->number);
        $this->assertEquals('2022-I0001', $invoice->number);

    }

    // public function testResetCounter()
    // {
    //     $timezone = Timezone::find(1);

    //     $date_formatted = now($timezone->name)->format('Ymd');

    //     $settings = $this->company->settings;
    //     $settings->invoice_number_pattern = '{$date:Ymd}-{$counter}';
    //     $settings->timezone_id = 1;
    //     $this->company->settings = $settings;
    //     $this->company->save();

    //     $this->client->settings = $settings;
    //     $this->client->save();

    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0001", $invoice_number);
    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0002", $invoice_number);
    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0003", $invoice_number);
    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0004", $invoice_number);

    //     $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
    //     $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
    //     $this->company->settings = $settings;
    //     $this->company->save();

    //     $this->client->settings = $settings;
    //     $this->client->save();
        
    //     $this->travel(5)->days();
    //     $date_formatted = now($timezone->name)->format('Ymd');

    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0001", $invoice_number);
        
    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0002", $invoice_number);

    //     $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
    //     $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
    //     $this->company->settings = $settings;
    //     $this->company->save();

    //     $this->travel(5)->days();
    //     $date_formatted = now($timezone->name)->format('Ymd');

    //     $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
    //     $this->assertEquals($date_formatted."-0001", $invoice_number);

    //     $this->travelBack();

    // }

    // public function testHasSharedCounter()
    // {
    //     $this->assertFalse($this->hasSharedCounter($this->client,));
    // }


}
