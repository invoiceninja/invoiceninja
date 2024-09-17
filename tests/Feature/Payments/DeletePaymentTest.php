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

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * 
 */
class DeletePaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testRegularPayment()
    {
        Invoice::factory()
                ->count(10)
                ->create([
                    'user_id' => $this->user->id,
                    'company_id' => $this->company->id,
                    'client_id' => $this->client->id,
                    'amount' => 101,
                    'balance' => 101,
                    'status_id' => Invoice::STATUS_SENT,
                    'paid_to_date' => 0,
                ]);

        $i = Invoice::where('amount', 101)->where('status_id', 2)->take(1)->get();

        $invoices = $i->map(function ($i) {
            return [
                'invoice_id' => $i->hashed_id,
                'amount' => $i->amount,
            ];
        })->toArray();

        $data = [
            'client_id' => $this->client->hashed_id,
            'amount' => 0,
            'invoices' => $invoices,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $i->fresh()->each(function ($i) {
            $this->assertEquals(0, $i->balance);
            $this->assertEquals(101, $i->paid_to_date);
            $this->assertEquals(Invoice::STATUS_PAID, $i->status_id);
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', ['ids' => [$arr['data']['id']]]);

        $response->assertStatus(200);

        $i->fresh()->each(function ($i) {
            $this->assertEquals(101, $i->balance);
            $this->assertEquals(0, $i->paid_to_date);
            $this->assertEquals(Invoice::STATUS_SENT, $i->status_id);
        });

    }
}
