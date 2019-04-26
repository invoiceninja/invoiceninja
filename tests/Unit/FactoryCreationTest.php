<?php

namespace Tests\Unit;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Factory\CloneInvoiceFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Factory\UserFactory;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 */
class FactoryCreationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;

    public function setUp() :void
    {
    
        parent::setUp();
    
        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();


        $this->account = factory(\App\Models\Account::class)->create();
                $this->company = factory(\App\Models\Company::class)->create([
                    'account_id' => $this->account->id,
                ]);

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->user = factory(\App\Models\User::class)->create([
        //    'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);
    }

    /**
     * @test
     * @covers      App\Factory\ProductFactory
     */
    public function testProductionCreation()
    {
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->save();

        $this->assertNotNull($product);

        $this->assertInternalType("int", $product->id);
    }

    /**
     * @test
     * @covers      App\Factory\InvoiceFactory
     */
    
    public function testInvoiceCreation()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);

        $client->save();

        $invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->save();

        $this->assertNotNull($invoice);

        $this->assertInternalType("int", $invoice->id);
    }

    /**
     * @test
     * @covers App|Factory\CloneInvoiceFactory
     */
    public function testCloneInvoiceCreation()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);

        $client->save();

        $invoice = InvoiceFactory::create($this->company->id,$this->user->id);//stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->save();

        $this->assertNotNull($invoice);

        $this->assertInternalType("int", $invoice->id);


        $clone = CloneInvoiceFactory::create($invoice, $this->user->id);
        $clone->save();

        $this->assertNotNull($clone);

        $this->assertInternalType("int", $clone->id);
        

    }

    /**
     * @test
     * @covers App|Factory\ClientFactory
     */
    public function testClientCreate()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);

        $client->save();

        $this->assertNotNull($client);

        $this->assertInternalType("int", $client->id);
    }

    /**
     * @test
     * @covers App|Factory\ClientContactFactory
     */
    public function testClientContactCreate()
    {

    factory(\App\Models\Client::class)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,2)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id
            ]);

        });

        $client = Client::whereUserId($this->user->id)->whereCompanyId($this->company->id)->first();


        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->save();

        $this->assertNotNull($contact);

        $this->assertInternalType("int", $contact->id);

    }

    /**
     * @test
     * @covers App|Factory\UserFactory
     */
    public function testUserCreate()
    {
        $new_user = UserFactory::create();
        $new_user->email = $this->faker->email;
        $new_user->save();

        $this->assertNotNull($new_user);

        $this->assertInternalType("int", $new_user->id);

    }


}
