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

    protected function setUp() :void
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
