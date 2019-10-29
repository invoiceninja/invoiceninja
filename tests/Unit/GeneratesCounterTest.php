<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\GeneratesNumberCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesCounter
 */
class GeneratesCounterTest extends TestCase
{
	use GeneratesCounter;
    use DatabaseTransactions;
    use MakesHash;
    //use MockAccountData;

    public function setUp() :void
    {

        parent::setUp();

        Session::start();
        $this->faker = \Faker\Factory::create();
        Model::reguard();
                $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
                        'domain' => 'ninja.test',

        ]);
        $account->default_company_id = $company->id;
        $account->save();
        $user = factory(\App\Models\User::class)->create([
        //    'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);
        $userPermissions = collect([
                                    'view_invoice',
                                    'view_client',
                                    'edit_client',
                                    'edit_invoice',
                                    'create_invoice',
                                    'create_client'
                                ]);
        $userSettings = DefaultSettings::userSettings();
        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);
        factory(\App\Models\Client::class)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){
            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);
            factory(\App\Models\ClientContact::class,2)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);
        });
        $this->client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();
    }


	public function testHasSharedCounter()
    {
        $this->assertFalse($this->hasSharedCounter($this->client));
    }

    public function testInvoiceNumberValue()
    {

    	$invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, 1);

    	$invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, 2);

    }

    public function testInvoiceNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_prefix = null;
        $settings->invoice_number_pattern = '{$year}-{$counter}';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $invoice_number = $this->getNextInvoiceNumber($this->client);
        $invoice_number2 = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, '2019-0001');
        $this->assertEquals($invoice_number2, '2019-0002');
        $this->assertEquals($this->client->company->settings->invoice_number_counter,3);
       
    }

    public function testInvoiceClientNumberPattern()
    {
        $settings = $this->client->company->settings;

        $settings->invoice_number_prefix = null;
        $settings->invoice_number_pattern = '{$year}-{$client_counter}';
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $settings = $this->client->settings;
        $settings->invoice_number_counter = 10;
        $this->client->settings = $settings;
        $this->client->save();

        $this->assertEquals($this->client->settings->invoice_number_counter,10);

        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, '2019-0010');
        
        $invoice_number = $this->getNextInvoiceNumber($this->client);
		$this->assertEquals($invoice_number, '2019-0011');
        
       
    }

    public function testInvoicePadding()
    {
        $settings = $this->client->company->settings;
        $settings->counter_padding = 5;
        $this->client->company->settings = $settings;
        $this->client->push();

        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($this->client->company->settings->counter_padding, 5);
        $this->assertEquals(strlen($invoice_number), 5);
        $this->assertEquals($invoice_number, '00001');


        $settings = $this->client->company->settings;
        $settings->counter_padding = 10;
        $this->client->company->settings = $settings;
        $this->client->push();

        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($this->client->company->settings->counter_padding, 10);
        $this->assertEquals(strlen($invoice_number), 10);
        $this->assertEquals($invoice_number, '0000000002');


    }

    public function testInvoicePrefix()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_prefix = 'X';
        $this->client->company->settings = $settings;
        $this->client->company->save();    

        $invoice_number = $this->getNextInvoiceNumber($this->client);
    
        $this->assertEquals($invoice_number, 'X0001');

        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, 'X0002');


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
        $settings = $this->client->company->settings;
        $settings->client_number_prefix = 'C';
        $this->client->company->settings = $settings;
        $this->client->company->save();    

        $client_number = $this->getNextClientNumber($this->client);
    
        $this->assertEquals($client_number, 'C0001');

        $client_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($client_number, 'C0002');


    }

    public function testClientNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->client_number_prefix = '';
        $settings->client_number_pattern = '{$year}-{$user_id}-{$counter}';
        $this->client->company->settings = $settings;
        $this->client->company->save();  
        $this->client->save();
        $this->client->fresh();  

        $client_number = $this->getNextClientNumber($this->client);
    
        $this->assertEquals($client_number, date('Y') . '-' . $this->client->user_id . '-0001');

        $client_number = $this->getNextClientNumber($this->client);
    
        $this->assertEquals($client_number, date('Y') . '-' . $this->client->user_id . '-0002');

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
        $this->assertEquals($this->getNextNumber($this->client), '2019-1');
        $this->assertEquals($this->getNextNumber($this->client), '2019-2');
       
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