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

use App\Factory\QuoteToRecurringQuoteFactory;
use App\Factory\RecurringQuoteToQuoteFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\RecurringQuote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\RecurringQuoteController
 */
class RecurringQuotesTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testRecurringQuoteList()
    {
        // Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
        //     ClientContact::factory()->create([
        //         'user_id' => $this->user->id,
        //         'client_id' => $c->id,
        //         'company_id' => $this->company->id,
        //         'is_primary' => 1,
        //     ]);

        //     ClientContact::factory()->create([
        //         'user_id' => $this->user->id,
        //         'client_id' => $c->id,
        //         'company_id' => $this->company->id,
        //     ]);
        // });

        // $client = Client::all()->first();

        // RecurringQuote::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_quotes');

        $response->assertStatus(200);
    }

    public function testRecurringQuoteRESTEndPoints()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_quotes/'.$this->recurring_quote->hashed_id);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_quotes/'.$this->recurring_quote->hashed_id.'/edit');

        $response->assertStatus(200);

        $RecurringQuote_update = [
            'status_id' => RecurringQuote::STATUS_DRAFT,
            'number' => 'customnumber',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_quotes/'.$this->recurring_quote->hashed_id, $RecurringQuote_update);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('customnumber', $arr['data']['number']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_quotes/'.$this->recurring_quote->hashed_id, $RecurringQuote_update)
            ->assertStatus(200);

        $RecurringQuote_update = [
            'status_id' => RecurringQuote::STATUS_DRAFT,
            'client_id' => $this->recurring_quote->hashed_id,
            'number' => 'customnumber',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_quotes/', $RecurringQuote_update)
            ->assertStatus(302);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/recurring_quotes/'.$this->encodePrimaryKey($this->recurring_quote->id));

        $response->assertStatus(200);
    }

    public function testSubscriptionIdPassesToQuote()
    {
        $recurring_invoice = QuoteToRecurringQuoteFactory::create($this->quote);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringQuote::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringQuoteNumber($this->quote->client, $this->quote);
        $recurring_invoice->subscription_id = 10;
        $recurring_invoice->save();

        $invoice = RecurringQuoteToQuoteFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(10, $invoice->subscription_id);
    }

    public function testSubscriptionIdPassesToQuoteIfNull()
    {
        $recurring_invoice = QuoteToRecurringQuoteFactory::create($this->quote);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringQuote::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringQuoteNumber($this->quote->client, $this->quote);
        $recurring_invoice->save();

        $invoice = RecurringQuoteToQuoteFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }

    public function testSubscriptionIdPassesToQuoteIfNothingSet()
    {
        $recurring_invoice = QuoteToRecurringQuoteFactory::create($this->quote);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringQuote::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringQuoteNumber($this->quote->client, $this->quote);
        $recurring_invoice->save();

        $invoice = RecurringQuoteToQuoteFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }
}
