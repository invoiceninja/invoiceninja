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

use App\DataMapper\InvoiceItem;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
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

    public function testPaidToDateAdjustments()
    {

        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'balance' => 0,
        ]);

        $ii = new InvoiceItem();
        $ii->cost = 100;
        $ii->quantity = 1;
        $ii->product_key = 'xx';
        $ii->notes = 'yy';

        $i = \App\Models\Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'discount' => 0,
            'line_items' => [
                $ii
            ],
            'status_id' => 1,
        ]);

        $i = $i->calc()->getInvoice();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(100, $i->amount);

        $i->service()->markSent()->save();

        $this->assertEquals(100, $i->balance);

        $i->service()->markPaid()->save();
        $i = $i->fresh();
        $c = $c->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(0, $c->balance);

        $this->assertEquals(100, $c->paid_to_date);

        $i->service()->handleReversal()->save();


        $data = $i->toArray();
        $data['invoice_id'] = $i->hashed_id;
        $data['user_id'] = $this->encodePrimaryKey($i->user_id);
        $data['client_id'] = $this->encodePrimaryKey($i->client_id);
        $data['status_id'] = 1;

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson("/api/v1/credits?mark_sent=true", $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $cr_id = $arr['data']['id'];

        $i = $i->fresh();
        $c = $c->fresh();

        $credit = $i->credits()->first();

        $this->assertNotNull($credit);

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(100, $c->credit_balance);
        $this->assertEquals(0, $c->paid_to_date);

        $credit->service()->deleteCredit()->save();

        $c = $c->fresh();

        $this->assertEquals(100, $c->paid_to_date);
        $this->assertEquals(0, $c->credit_balance);

        $credit->service()->restoreCredit()->save();

        $c = $c->fresh();

        $this->assertEquals(0, $c->paid_to_date);
        $this->assertEquals(100, $c->credit_balance);

    }

    public function testCreditPaymentsPaidToDates()
    {
        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'balance' => 100,
        ]);

        $ii = new InvoiceItem();
        $ii->cost = 100;
        $ii->quantity = 1;
        $ii->product_key = 'xx';
        $ii->notes = 'yy';

        $i = \App\Models\Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'discount' => 0,
            'line_items' => [
                $ii
            ],
            'status_id' => 1,
        ]);

        $i->save();

        $i->calc()->getInvoice();

        $i->service()->markSent()->save();

        $cr = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'discount' => 0,
            'line_items' => [
                $ii
            ],
            'status_id' => 1,
        ]);


        $cr->calc()->getCredit();

        $cr->service()->markSent()->save();

        $this->assertEquals(100, $i->balance);
        $this->assertEquals(100, $i->amount);
        $this->assertEquals(0, $i->paid_to_date);
        $this->assertEquals(2, $i->status_id);

        $this->assertEquals(100, $cr->balance);
        $this->assertEquals(100, $cr->amount);
        $this->assertEquals(0, $cr->paid_to_date);
        $this->assertEquals(2, $cr->status_id);

        $this->assertEquals(100, $c->balance);
        $this->assertEquals(0, $c->paid_to_date);

        $data = [
            'date' => '2020/12/12',
            'client_id' => $c->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 100
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $cr->hashed_id,
                    'amount' => 100
                ]
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $p_id = $arr['data']['id'];
        $i = $i->fresh();
        $cr = $cr->fresh();
        $c = $c->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(100, $i->paid_to_date);
        $this->assertEquals(4, $i->status_id);

        $this->assertEquals(0, $cr->balance);
        $this->assertEquals(100, $cr->paid_to_date);
        $this->assertEquals(4, $i->status_id);

        $this->assertEquals(100, $c->paid_to_date);
        $this->assertEquals(0, $c->balance);

        $p = \App\Models\Payment::find($this->decodePrimaryKey($p_id));

        $this->assertEquals(0, $p->amount);
        $this->assertEquals(0, $p->applied);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->deleteJson("/api/v1/payments/{$p_id}");

        $response->assertStatus(200);

        $i = $i->fresh();
        $cr = $cr->fresh();
        $c = $c->fresh();

        $this->assertEquals(100, $i->balance);
        $this->assertEquals(100, $i->amount);
        $this->assertEquals(0, $i->paid_to_date);
        $this->assertEquals(2, $i->status_id);

        $this->assertEquals(100, $cr->balance);
        $this->assertEquals(100, $cr->amount);
        $this->assertEquals(2, $cr->status_id);
        $this->assertEquals(0, $cr->paid_to_date);

        $this->assertEquals(100, $c->balance);
        $this->assertEquals(0, $c->paid_to_date);


        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->deleteJson("/api/v1/credits/{$cr->hashed_id}");

        $response->assertStatus(200);

        $cr = $cr->fresh();

        $this->assertEquals(true, $cr->is_deleted);

        $this->assertEquals(100, $c->balance);
        $this->assertEquals(0, $c->paid_to_date);


    }

    public function testApplicableFilters()
    {
        Credit::where('company_id', $this->company->id)->cursor()->each(function ($c) { $c->forceDelete(); });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(0, $arr['data']);

        $c = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => Credit::STATUS_DRAFT,
            'due_date' => null,
            'date' => now(),
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertCount(1, $arr['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?applicable=true');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertCount(0, $arr['data']);

        $c->status_id = Credit::STATUS_SENT;
        $c->amount = 20;
        $c->balance = 20;
        $c->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?applicable=true');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(1, $arr['data']);

        $c->status_id = Credit::STATUS_SENT;
        $c->amount = 20;
        $c->balance = 20;
        $c->due_date = now()->subYear();
        $c->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?applicable=true');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(0, $arr['data']);

        $c->status_id = Credit::STATUS_SENT;
        $c->amount = 20;
        $c->balance = 20;
        $c->due_date = now()->addYear();
        $c->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?applicable=true');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(1, $arr['data']);

        $c->status_id = Credit::STATUS_APPLIED;
        $c->amount = 20;
        $c->balance = 20;
        $c->due_date = now()->addYear();
        $c->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?applicable=true');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertCount(0, $arr['data']);



    }

    public function testQuoteDownloadPDF()
    {
        $i = $this->credit->invitations->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/credit/{$i->key}/download");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }


    public function testBulkActions()
    {
        $data = [
            'action' => 'archive',
            'ids' => [$this->credit->hashed_id]
        ];

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk', $data)
          ->assertStatus(200);


        $data = [
            'ids' => [$this->credit->hashed_id],
            'action' => 'restore'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk', $data)
          ->assertStatus(200);

        $data = [
            'ids' => [$this->credit->hashed_id],
            'action' => 'delete'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/bulk', $data)
          ->assertStatus(200);
    }


    public function testCreditGetClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits?client_status=draft'.$this->encodePrimaryKey($this->bank_transaction->id));

        $response->assertStatus(200);
    }

    public function testCreditsList()
    {
        Client::factory()->count(3)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
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

        Credit::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits');

        $response->assertStatus(200);
    }

    public function testCreditRESTEndPoints()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id).'/edit');

        $response->assertStatus(200);

        $credit_update = [
            'tax_name1' => 'dippy',
        ];

        $this->assertNotNull($this->credit);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id), $credit_update)
            ->assertStatus(200);
    }

    public function testPostNewCredit()
    {
        $credit = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '34343xx43',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/', $credit)
            ->assertStatus(200);
    }

    public function testDeleteCredit()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id));

        $response->assertStatus(200);
    }

    public function testDuplicateNumberCatch()
    {
        $data = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits', $data);

        $response->assertStatus(302);
    }

    public function testCreditPut()
    {
        $data = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/'.$this->encodePrimaryKey($this->credit->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/', $data);

        $response->assertStatus(302);
    }
}
