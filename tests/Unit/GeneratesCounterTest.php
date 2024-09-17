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
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Timezone;
use Tests\MockAccountData;
use App\Models\GroupSetting;
use App\Factory\ClientFactory;
use App\Factory\VendorFactory;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\DataMapper\ClientSettings;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *   App\Utils\Traits\GeneratesCounter
 */
class GeneratesCounterTest extends TestCase
{
    use GeneratesCounter;
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        $this->faker = \Faker\Factory::create();
        Model::reguard();

        $this->makeTestData();
    }

    public function testResetCounterGroup()
    {
        $timezone = Timezone::find(1);

        $date_formatted = now($timezone->name)->format('Ymd');

        $gs = new GroupSetting();
        $gs->name = 'Test';
        $gs->company_id = $this->client->company_id;
        $gs->settings = ClientSettings::buildClientSettings($this->company->settings, $this->client->settings);
        $gs->save();

        $this->client->group_settings_id = $gs->id;
        $this->client->save();

        $settings = $gs->settings;
        // $settings = $this->client->settings;
        $settings->invoice_number_pattern = '{$date:Ymd}-{$group_counter}';
        $settings->timezone_id = 1;
        $gs->settings = $settings;
        $gs->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0003', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0004', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $gs->settings = $settings;
        $gs->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->invoice->number = $invoice_number;
        $this->invoice->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $gs->settings = $settings;
        $gs->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->travelBack();
    }


    public function testResetCounterClient()
    {
        $timezone = Timezone::find(1);

        $date_formatted = now($timezone->name)->format('Ymd');

        $settings = $this->client->settings;
        $settings->invoice_number_pattern = '{$date:Ymd}-{$client_counter}';
        $settings->timezone_id = 1;
        $this->client->settings = $settings;
        $this->client->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0003', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0004', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $this->client->settings = $settings;
        $this->client->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->invoice->number = $invoice_number;
        $this->invoice->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $this->client->settings = $settings;
        $this->client->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->travelBack();
    }

    public function testResetCounter()
    {
        $timezone = Timezone::find(1);

        $date_formatted = now($timezone->name)->format('Ymd');

        $settings = $this->company->settings;
        $settings->invoice_number_pattern = '{$date:Ymd}-{$counter}';
        $settings->timezone_id = 1;
        $this->company->settings = $settings;
        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0003', $invoice_number);
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0004', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $this->company->settings = $settings;
        $this->company->save();

        // $this->client->settings = $settings;
        // $this->client->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->invoice->number = $invoice_number;
        $this->invoice->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0002', $invoice_number);

        $settings->reset_counter_date = now($timezone->name)->format('Y-m-d');
        $settings->reset_counter_frequency_id = RecurringInvoice::FREQUENCY_DAILY;
        $this->company->settings = $settings;
        $this->company->save();

        $this->travel(5)->days();
        $date_formatted = now($timezone->name)->format('Ymd');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());
        $this->assertEquals($date_formatted.'-0001', $invoice_number);

        $this->travelBack();
    }

    public function testHasSharedCounter()
    {
        $this->assertFalse($this->hasSharedCounter($this->client, ));
    }

    public function testHasTrueSharedCounter()
    {
        $settings = $this->client->getMergedSettings();
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $this->company->settings = $settings;

        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $gs = $this->client->group_settings;
        $gs->settings = $settings;
        $gs->save();

        $this->assertTrue($this->hasSharedCounter($this->client));
    }

    public function testNoCounterBeingSpecifiedInCounterStringStub()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = 'test-{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $invoice_number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->assertEquals('test-0001', $invoice_number);
    }

    public function testNoCounterBeingSpecifiedInCounterStringWithFix()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_counter = 100;
        $settings->invoice_number_pattern = 'test-';
        $settings->shared_invoice_quote_counter = 100;
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $invoice_number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->assertEquals('test-0100', $invoice_number);
    }

    public function testInvoiceNumberValue()
    {
        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());

        $this->assertEquals($invoice_number, '0002');

        $invoice_number = $this->getNextInvoiceNumber($this->client->fresh(), $this->invoice->fresh());

        $this->assertEquals($invoice_number, '0003');
    }

    public function testQuoteNumberValue()
    {
        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id,
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
        ]);

        $quote_number = $this->getNextQuoteNumber($this->client->fresh(), $quote);

        $this->assertEquals($quote_number, 0002);
    }

    public function testInvoiceNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-{$counter}';
        $settings->timezone_id = '31';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $invoice_number = $this->getNextInvoiceNumber($this->client, $this->invoice);
        $invoice_number2 = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->assertEquals($invoice_number, date('Y').'-0001');
        $this->assertEquals($invoice_number2, date('Y').'-0002');
        $this->assertEquals($this->client->company->settings->invoice_number_counter, 3);
    }

    public function testQuoteNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->quote_number_counter = 1;
        $settings->quote_number_pattern = '{$year}-{$counter}';
        $settings->timezone_id = '31';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id,
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
        ]);

        $quote_number = $this->getNextQuoteNumber($this->client, $quote);
        $quote_number2 = $this->getNextQuoteNumber($this->client, $quote);

        $this->assertEquals($quote_number, date('Y').'-0001');
        $this->assertEquals($quote_number2, date('Y').'-0002');
        $this->assertEquals($this->client->company->settings->quote_number_counter, 3);
    }

    public function testQuoteNumberPatternWithSharedCounter()
    {
        $settings = $this->client->company->settings;
        $settings->quote_number_counter = 100;
        $settings->invoice_number_counter = 1000;
        $settings->quote_number_pattern = '{$year}-{$counter}';
        $settings->shared_invoice_quote_counter = true;

        $settings->timezone_id = '31';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $gs = $this->client->group_settings;
        $gs->settings = $settings;
        $gs->save();

        $quote = Quote::factory()->create([
            'user_id' => $this->client->user_id,
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
        ]);

        $quote_number = $this->getNextQuoteNumber($this->client, $quote);
        $quote_number2 = $this->getNextQuoteNumber($this->client, $quote);

        $this->assertEquals($quote_number, date('Y').'-1000');
        $this->assertEquals($quote_number2, date('Y').'-1001');
        $this->assertEquals($this->client->company->settings->quote_number_counter, 100);
    }

    public function testInvoiceClientNumberPattern()
    {
        $settings = $this->company->settings;
        $settings->client_number_pattern = '{$year}-{$client_counter}';
        $settings->client_number_counter = 10;

        $settings->timezone_id = '31';

        $this->company->settings = $settings;
        $this->company->save();

        $settings = $this->client->settings;
        $settings->client_number_pattern = '{$year}-{$client_counter}';
        $settings->client_number_counter = 10;
        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $this->assertEquals($this->client->settings->client_number_counter, 10);
        $this->assertEquals($this->client->getSetting('client_number_pattern'), '{$year}-{$client_counter}');

        $invoice_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($invoice_number, date('Y').'-0010');
        $this->client->number = $invoice_number;
        $this->client->save();

        $invoice_number = $this->getNextClientNumber($this->client);
        $this->assertEquals($invoice_number, date('Y').'-0011');
    }

    public function testInvoicePadding()
    {
        $settings = $this->company->settings;
        $settings->counter_padding = 5;
        $settings->invoice_number_counter = 7;
        //$this->client->settings = $settings;

        $settings->timezone_id = '31';

        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();
        $invoice_number = $this->getNextInvoiceNumber($cliz, $this->invoice);

        $this->assertEquals($cliz->getSetting('counter_padding'), 5);
        $this->assertEquals($invoice_number, '00007');
        $this->assertEquals(strlen($invoice_number), 5);

        $settings = $this->company->settings;
        $settings->counter_padding = 10;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $invoice_number = $this->getNextInvoiceNumber($cliz, $this->invoice);

        $this->assertEquals($cliz->getSetting('counter_padding'), 10);
        $this->assertEquals(strlen($invoice_number), 10);
        $this->assertEquals($invoice_number, '0000000007');
    }

    public function testInvoicePrefix()
    {
        $settings = $this->company->settings;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $invoice_number = $this->getNextInvoiceNumber($cliz->fresh(), $this->invoice);

        $this->assertEquals($invoice_number, '0002');

        $invoice_number = $this->getNextInvoiceNumber($cliz->fresh(), $this->invoice);

        $this->assertEquals($invoice_number, '0003');
    }

    public function testClientNumber()
    {
        $client_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($client_number, '0001');

        $client_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($client_number, '0002');
    }

    public function testClientNumberPrefix()
    {
        $settings = $this->company->settings;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, '0001');

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, '0002');
    }

    public function testClientNumberPattern()
    {
        $settings = $this->company->settings;
        $settings->client_number_pattern = '{$year}-{$user_id}-{$counter}';

        $settings->timezone_id = '31';

        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, date('Y').'-'.str_pad($this->client->user_id, 2, '0', STR_PAD_LEFT).'-0001');

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, date('Y').'-'.str_pad($this->client->user_id, 2, '0', STR_PAD_LEFT).'-0002');
    }

    public function testVendorNumberPattern()
    {
        $settings = $this->company->settings;
        $settings->vendor_number_pattern = '{$year}-{$user_id}-{$counter}';

        $settings->timezone_id = '31';

        $this->company->settings = $settings;
        $this->company->save();

        $vendor = VendorFactory::create($this->company->id, $this->user->id);
        $vendor->save();

        $vendor_number = $this->getNextVendorNumber($vendor);

        $this->assertEquals($vendor_number, date('Y').'-'.str_pad($vendor->user_id, 2, '0', STR_PAD_LEFT).'-0001');

        $vendor_number = $this->getNextVendorNumber($vendor);

        $this->assertEquals($vendor_number, date('Y').'-'.str_pad($vendor->user_id, 2, '0', STR_PAD_LEFT).'-0002');
    }

    /*

        public function testClientNextNumber()
        {
            $this->assertEquals($this->getNextNumber($this->client),1);
        }
        public function testRecurringInvoiceNumberPrefix()
        {
            //$this->assertEquals($this->getNextNumber(RecurringInvoice::class), 'R1');
            $this->assertEquals($this->getCounter($this->client), 1);

        }
        public function testClientIncrementer()
        {
            $this->incrementCounter($this->client);
            $this->assertEquals($this->getCounter($this->client), 2);
        }
    /*
        public function testCounterValues()
        {
            $this->assertEquals($this->getCounter(Invoice::class), 1);
            $this->assertEquals($this->getCounter(RecurringInvoice::class), 1);
            $this->assertEquals($this->getCounter(Credit::class), 1);
        }
        public function testClassIncrementers()
        {
            $this->client->incrementCounter(Invoice::class);
            $this->client->incrementCounter(RecurringInvoice::class);
            $this->client->incrementCounter(Credit::class);
            $this->assertEquals($this->getCounter(Invoice::class), 3);
            $this->assertEquals($this->getCounter(RecurringInvoice::class), 3);
            $this->assertEquals($this->getCounter(Credit::class), 2);
        }

        public function testClientNumberPattern()
        {
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$year}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);
            $company = Company::find($this->client->company_id);
            $this->assertEquals($company->settings->client_number_counter,1);
            $this->assertEquals($this->getNextNumber($this->client), date('y').'-1');
            $this->assertEquals($this->getNextNumber($this->client), date('y').'-2');

            $company = Company::find($this->client->company_id);
            $this->assertEquals($company->settings->client_number_counter,2);
            $this->assertEquals($this->client->settings->client_number_counter,1);
        }
        public function testClientNumberPatternWithDate()
        {
            date_default_timezone_set('US/Eastern');
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$date:j}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);

            $this->assertEquals($this->getNextNumber($this->client), date('j') . '-1');
        }
        public function testClientNumberPatternWithDate2()
        {
            date_default_timezone_set('US/Eastern');
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$date:d M Y}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);

            $this->assertEquals($this->getNextNumber($this->client), date('d M Y') . '-1');
        }
     */
}
