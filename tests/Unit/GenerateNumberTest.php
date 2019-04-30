<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use App\Models\Client;
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
    }    


    public function testEntityName()
    {

        $this->assertEquals($this->entityName(Client::class), 'client');

    }

    public function testCounterVariables()
    {
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

        $client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();

        $this->assertEquals($client->getCounter(Client::class), 1);

        $this->assertEquals($client->getNextNumber(Client::class),1);

        $settings = $client->getSettingsByKey('recurring_invoice_number_prefix');
        $settings->recurring_invoice_number_prefix = 'R';
        $client->setSettingsByEntity($settings->entity, $settings);

        $this->assertEquals($client->getNextNumber(RecurringInvoice::class), 'R1');

        $client->incrementCounter(Client::class);

        $this->assertEquals($client->getCounter(Client::class), 2);
    }
}
