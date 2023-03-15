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

namespace Tests\Feature;

use App\Factory\InvoiceItemFactory;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Jobs\RecurringInvoice\UpdateRecurring;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\RecurringInvoice;
use App\Utils\Helpers;
use App\Utils\Traits\MakesHash;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\RecurringInvoiceController
 */
class RecurringInvoiceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testBulkIncreasePriceWithJob()
    {

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();
    
        (new UpdateRecurring([$recurring_invoice->id], $this->company, $this->user, 'increase_prices', 10))->handle();

        $recurring_invoice->refresh();

        $this->assertEquals(11, $recurring_invoice->amount);

    }

    public function testBulkUpdateWithJob()
    {
        $p = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'product_key' => 'pink',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();
    
        (new UpdateRecurring([$recurring_invoice->id], $this->company, $this->user, 'update_prices'))->handle();

        $recurring_invoice->refresh();

        $this->assertEquals(20, $recurring_invoice->amount);

    }

    public function testBulkUpdatePrices()
    {
        $p = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'product_key' => 'pink',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $this->assertEquals(10, $recurring_invoice->amount);

        $p->cost = 20;
        $p->save();

        $recurring_invoice->service()->updatePrice();

        $recurring_invoice->refresh();

        $this->assertEquals(20, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(10);

        $recurring_invoice->refresh();

        $this->assertEquals(22, $recurring_invoice->amount);


    }

    public function testBulkUpdateMultiPrices()
    {
        $p1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'product_key' => 'pink',
        ]);

         $p2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'product_key' => 'floyd',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'floyd',
            'notes' => 'test',
            'cost' => 20,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];

        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $this->assertEquals(30, $recurring_invoice->amount);

        $p1->cost = 20;
        $p1->save();

        $p2->cost = 40;
        $p2->save();

        $recurring_invoice->service()->updatePrice();

        $recurring_invoice->refresh();

        $this->assertEquals(60, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(10);

        $recurring_invoice->refresh();

        $this->assertEquals(66, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(1);

        $recurring_invoice->refresh();

        $this->assertEquals(66.66, $recurring_invoice->amount);

    }


    public function testRecurringGetStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?client_status=active')
          ->assertStatus(200);
    }


    public function testPostRecurringInvoiceWithPlaceholderVariables()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->task_id = $this->encodePrimaryKey($this->task->id);
        $item->expense_id = $this->encodePrimaryKey($this->expense->id);
        $item->notes = "Hello this is the month of :MONTH";

        $line_items[] = $item;


        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $line_items,
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_DRAFT, $arr['data']['status_id']);

        $notes = end($arr['data']['line_items'])['notes'];

        $this->assertTrue(str_contains($notes, ':MONTH'));
    }


    public function testPostRecurringInvoice()
    {
        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $this->buildLineItems(),
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_DRAFT, $arr['data']['status_id']);
    }

    public function testPostRecurringInvoiceWithStartAndStop()
    {
        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $this->buildLineItems(),
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices?start=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $arr['data']['status_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$arr['data']['id'].'?stop=true', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_PAUSED, $arr['data']['status_id']);
    }

    public function testRecurringInvoiceList()
    {
        Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $client = Client::all()->first();

        RecurringInvoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices');

        $response->assertStatus(200);
    }

    public function testRecurringInvoiceRESTEndPoints()
    {
        Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $client = Client::query()->orderBy('id', 'DESC')->first();

        RecurringInvoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $RecurringInvoice = RecurringInvoice::query()->where('user_id', $this->user->id)->orderBy('id', 'DESC')->first();
        $RecurringInvoice->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id).'/edit');

        $response->assertStatus(200);

        $RecurringInvoice_update = [
            'status_id' => RecurringInvoice::STATUS_DRAFT,
            'client_id' => $this->encodePrimaryKey($RecurringInvoice->client_id),
            'number' => 'customnumber',
        ];

        $this->assertNotNull($RecurringInvoice);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id), $RecurringInvoice_update)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('customnumber', $arr['data']['number']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id), $RecurringInvoice_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $RecurringInvoice_update)
            ->assertStatus(302);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id));

        $response->assertStatus(200);
    }

    public function testSubscriptionIdPassesToInvoice()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->subscription_id = 10;
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(10, $invoice->subscription_id);
    }

    public function testRecurringDatePassesToInvoice()
    {
        $noteText = "Hello this is for :MONTH_AFTER";
        $recurringDate = \Carbon\Carbon::now()->timezone($this->client->timezone()->name)->subDays(10);

        $item = InvoiceItemFactory::create();
        $item->cost = 10;
        $item->notes = $noteText;

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);


        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = $recurringDate;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = $recurringDate;
        $recurring_invoice->line_items = [$item];
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->subscription_id = 10;
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->invoice->client);

        $expectedNote = Helpers::processReservedKeywords($noteText, $this->invoice->client, $recurringDate);

        $this->assertEquals($expectedNote, $invoice->line_items[0]->notes);
    }

    public function testSubscriptionIdPassesToInvoiceIfNull()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }

    public function testSubscriptionIdPassesToInvoiceIfNothingSet()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }
}
