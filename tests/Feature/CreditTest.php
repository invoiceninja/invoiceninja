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
