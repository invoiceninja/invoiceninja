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

use App\DataMapper\ClientSettings;
use App\Exceptions\QuoteConversion;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\QuoteController
 */
class QuoteTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testQuoteDueDateInjectionValidationLayer()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'partial_due_date' => now()->format('Y-m-d'),
            'partial' => 1,
            'amount' => 20,
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $arr = $response->json();
        // nlog($arr);

        $this->assertNotEmpty($arr['data']['due_date']);

    }

    public function testNullDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => '',
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEmpty($arr['data']['due_date']);

        $response = $this->withHeaders([
                            'X-API-SECRET' => config('ninja.api_secret'),
                            'X-API-TOKEN' => $this->token,
                        ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEmpty($arr['data']['due_date']);

    }


    public function testNonNullDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDays(10),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['due_date']);

        $response = $this->withHeaders([
                            'X-API-SECRET' => config('ninja.api_secret'),
                            'X-API-TOKEN' => $this->token,
                        ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['due_date']);

    }

    public function testPartialDueDates()
    {

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDay()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['due_date']);
        $this->assertEmpty($arr['data']['partial_due_date']);

        $data = [
            'client_id' => $this->client->hashed_id,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'partial' => 1,
            'partial_due_date' => now()->format('Y-m-d'),
            'amount' => 20,
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(now()->addDay()->format('Y-m-d'), $arr['data']['due_date']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['partial_due_date']);
        $this->assertEquals(1, $arr['data']['partial']);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->putJson('/api/v1/quotes/'.$arr['data']['id'], $arr['data']);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(now()->addDay()->format('Y-m-d'), $arr['data']['due_date']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['partial_due_date']);
        $this->assertEquals(1, $arr['data']['partial']);

    }

    public function testQuoteToProjectConversion2()
    {
        $settings = ClientSettings::defaults();
        $settings->default_task_rate = 41;

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $settings,
        ]);

        $q = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'status_id' => 2,
            'date' => now(),
            'line_items' => [
                [
                    'type_id' => '2',
                    'cost' => 200,
                    'quantity' => 2,
                    'notes' => 'Test200',
                ],
                [
                    'type_id' => '2',
                    'cost' => 100,
                    'quantity' => 1,
                    'notes' => 'Test100',
                ],
                [
                    'type_id' => '1',
                    'cost' => 10,
                    'quantity' => 1,
                    'notes' => 'Test',
                ],

            ],
        ]);

        $q->calc()->getQuote();
        $q->fresh();

        $p = $q->service()->convertToProject();

        $this->assertEquals(3, $p->budgeted_hours);
        $this->assertEquals(2, $p->tasks()->count());

        $t = $p->tasks()->where('description', 'Test200')->first();

        $this->assertEquals(200, $t->rate);

        $t = $p->tasks()->where('description', 'Test100')->first();

        $this->assertEquals(100, $t->rate);


    }

    public function testQuoteToProjectConversion()
    {
        $project = $this->quote->service()->convertToProject();

        $this->assertInstanceOf('\App\Models\Project', $project);
    }

    public function testQuoteConversion()
    {
        $invoice = $this->quote->service()->convertToInvoice();

        $this->assertInstanceOf('\App\Models\Invoice', $invoice);

        $this->expectException(QuoteConversion::class);

        $invoice = $this->quote->service()->convertToInvoice();

    }

    public function testQuoteDownloadPDF()
    {
        $i = $this->quote->invitations->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/quote/{$i->key}/download");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }

    public function testQuoteListApproved()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes?client_status=approved');

        $response->assertStatus(200);
    }


    public function testQuoteConvertToProject()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/bulk', ['action' => 'convert_to_project', 'ids' => [$this->quote->hashed_id]]);

        $response->assertStatus(200);

        $res = $response->json();

        $this->assertNotNull($res['data'][0]['project_id']);

        $project = Project::find($this->decodePrimaryKey($res['data'][0]['project_id']));

        $this->assertEquals($project->name, ctrans('texts.quote_number_short') . " " . $this->quote->number." [{$this->quote->client->present()->name()}]");
    }

    public function testQuoteList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes');

        $response->assertStatus(200);
    }

    public function testQuoteRESTEndPoints()
    {
        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id));
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        if ($response) {
            $response->assertStatus(200);
        }

        $this->assertNotNull($response);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id).'/edit');

        $response->assertStatus(200);

        $quote_update = [
            'status_id' => Quote::STATUS_APPROVED,
            'client_id' => $this->encodePrimaryKey($this->quote->client_id),
            'number'    => 'Rando',
        ];

        $this->assertNotNull($this->quote);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id), $quote_update);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id), $quote_update);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes/', $quote_update);

        $response->assertStatus(302);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/quotes/'.$this->encodePrimaryKey($this->quote->id));

        $response->assertStatus(200);

        $client_contact = ClientContact::whereClientId($this->client->id)->first();

        $data = [
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'date' => '2019-12-14',
            'line_items' => [],
            'invitations' => [
                ['client_contact_id' => $this->encodePrimaryKey($client_contact->id)],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes', $data);

        $response->assertStatus(200);
    }
}
