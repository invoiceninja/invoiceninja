<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesNumberCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesNumberCounter
 */
class GenerateNumberTest extends TestCase
{

    use GeneratesNumberCounter;
    use MakesHash;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

                $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
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


    public function testEntityName()
    {

        $this->assertEquals($this->entityName($this->client), 'client');

    }

    public function testSharedCounter()
    {

        $this->assertFalse($this->client->hasSharedCounter());

    }

    public function testClientCounterValue()
    {

         $this->assertEquals($this->client->getCounter($this->client), 1);

    }

    public function testClientNextNumber()
    {

        $this->assertEquals($this->client->getNextNumber($this->client),1);

    }

    public function testRecurringInvoiceNumberPrefix()
    {

        $this->assertEquals($this->client->getNextNumber(RecurringInvoice::class), 'R1');     
        $this->assertEquals($this->client->getCounter($this->client), 1);
   
    }

    public function testClientIncrementer()
    {
        $this->client->incrementCounter($this->client);

        $this->assertEquals($this->client->getCounter($this->client), 2);
    }

    public function testCounterValues()
    {


        $this->assertEquals($this->client->getCounter(Invoice::class), 1);
        $this->assertEquals($this->client->getCounter(RecurringInvoice::class), 1);
        $this->assertEquals($this->client->getCounter(Credit::class), 1);


    }

    public function testClassIncrementers()
    {

        $this->client->incrementCounter(Invoice::class);
        $this->client->incrementCounter(RecurringInvoice::class);
        $this->client->incrementCounter(Credit::class);

        $this->assertEquals($this->client->getCounter(Invoice::class), 3);
        $this->assertEquals($this->client->getCounter(RecurringInvoice::class), 3);
        $this->assertEquals($this->client->getCounter(Credit::class), 2);


    }

    /**
     * {$counter}
     * {$userId}
     * {$year}
     * {$date:format} - See options
     * @return [type] [description]
     */
    public function testClientNumberPattern()
    {

        $settings = $this->client->getSettingsByKey('client_number_pattern');
        $settings->client_number_pattern = '{$year}-{$counter}';
        $this->client->setSettingsByEntity($settings->entity, $settings);
        $this->assertEquals($this->client->getNextNumber($this->client), '2019-1');
        $this->assertEquals($this->client->getNextNumber($this->client), '2019-2');

        $company = Company::find($this->client->company_id);

        $this->assertEquals($company->settings->client_number_counter,3);
    }

    public function testClientNumberPatternWithDate()
    {

        date_default_timezone_set('US/Eastern');

        $settings = $this->client->getSettingsByKey('client_number_pattern');
        $settings->client_number_pattern = '{$date:j}-{$counter}';  
        $this->client->setSettingsByEntity($settings->entity, $settings);
        
        $this->assertEquals($this->client->getNextNumber($this->client), date('j') . '-1');
    }

    public function testClientNumberPatternWithDate2()
    {
        date_default_timezone_set('US/Eastern');

        $settings = $this->client->getSettingsByKey('client_number_pattern');
        $settings->client_number_pattern = '{$date:d M Y}-{$counter}';  
        $this->client->setSettingsByEntity($settings->entity, $settings);
        
        $this->assertEquals($this->client->getNextNumber($this->client), date('d M Y') . '-1');
    }
}
