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

use App\Factory\ClientFactory;
use App\Factory\CloneInvoiceFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Factory\UserFactory;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class FactoryCreationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    /**
     * 
     *       App\Factory\ProductFactory
     */
    public function testProductionCreation()
    {
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->save();

        $this->assertNotNull($product);

        $this->assertIsInt($product->id);
    }

    /**
     * 
     *       App\Factory\InvoiceFactory
     */
    public function testInvoiceCreation()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);

        $client->save();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->save();

        $this->assertNotNull($invoice);

        $this->assertIsInt($invoice->id);
    }

    /**
     * 
     *  App\Factory\CloneInvoiceFactory
     */
    public function testCloneInvoiceCreation()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);

        $client->save();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->save();

        $this->assertNotNull($invoice);

        $this->assertIsInt($invoice->id);

        $clone = CloneInvoiceFactory::create($invoice, $this->user->id);
        $clone->save();

        $this->assertNotNull($clone);

        $this->assertIsInt($clone->id);
    }

    /**
     * 
     *  App\Factory\ClientFactory
     */
    public function testClientCreate()
    {
        $cliz = ClientFactory::create($this->company->id, $this->user->id);

        $cliz->save();

        $this->assertNotNull($cliz);

        $this->assertIsInt($cliz->id);
    }

    /**
     * 
     *  App\Factory\ClientContactFactory
     */
    public function testClientContactCreate()
    {
        $cliz = ClientFactory::create($this->company->id, $this->user->id);

        $cliz->save();

        $this->assertNotNull($cliz->contacts);
        $this->assertEquals(0, $cliz->contacts->count());
        $this->assertIsInt($cliz->id);
    }

    /**
     * 
     *  App\Factory\UserFactory
     */
    public function testUserCreate()
    {
        $new_user = UserFactory::create($this->account->id);
        $new_user->email = $this->faker->freeEmail();
        $new_user->save();

        $this->assertNotNull($new_user);

        $this->assertIsInt($new_user->id);
    }
}
