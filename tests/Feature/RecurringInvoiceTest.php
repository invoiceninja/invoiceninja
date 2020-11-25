<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
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

    public function setUp() :void
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
        $client = Client::all()->first();

        RecurringInvoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $RecurringInvoice = RecurringInvoice::where('user_id', $this->user->id)->first();
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
        ];

        $this->assertNotNull($RecurringInvoice);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id), $RecurringInvoice_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->delete('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id));

        $response->assertStatus(200);
    }
}
