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

use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * @test
 */
class UnappliedPaymentRefundTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUnappliedPaymentRefund()
    {
        $data = [
            'amount' => 1000,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $this->assertEquals(1000, $this->client->fresh()->paid_to_date);

            $payment_id = $arr['data']['id'];

            $this->assertEquals(1000, $arr['data']['amount']);

            $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

            $data = [
                'id' => $this->encodePrimaryKey($payment->id),
                'amount' => 500,
                'date' => '2020/12/12',
            ];

            try {
                $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->post('/api/v1/payments/refund', $data);
            } catch (ValidationException $e) {
                $message = json_decode($e->validator->getMessageBag(), 1);
                $this->assertNotNull($message);
            }

            $response->assertStatus(200);

            $this->assertEquals(500, $this->client->fresh()->paid_to_date);
        }
    }
}
