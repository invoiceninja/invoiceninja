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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 */
class StorePaymentValidationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testNumericParse()
    {
        $this->assertFalse(is_numeric('2760.0,139.14'));
    }

    public function testNoAmountGiven()
    {
        $data = [
            // 'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $this->credit->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);
    }

    public function testInValidPaymentAmount()
    {
        $data = [
            'amount' => '10,33',
            'client_id' => $this->client->hashed_id,
            'invoices' => [
            ],
            'date' => '2019/12/12',
        ];

        $response = false;


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);


        $response->assertStatus(422);
    }

    public function testZapierArrayAmountReturnsValidationError()
    {
        $second_invoice = $this->invoice->replicate();
        $second_invoice->number = 'DUMMY-ZAPIER-002';
        $second_invoice->save();

        $data = [
            'amount' => [42.5, 17.5],
            'client_id' => $this->client->hashed_id,
            'date' => '2026-01-15T00:00:00+00:00',
            'invoice_id' => [$this->invoice->hashed_id, $second_invoice->hashed_id],
            'invoices' => [
                [
                    'amount' => 42.5,
                    'invoice_id' => $this->invoice->hashed_id,
                ],
                [
                    'amount' => 17.5,
                    'invoice_id' => $second_invoice->hashed_id,
                ],
            ],
            'private_notes' => 'Dummy bank statement payment',
            'transaction_reference' => 'dummy-zapier-payment',
            'type_id' => '1',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'User-Agent' => 'Zapier',
        ])->postJson('/api/v1/payments', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
        $this->assertDatabaseMissing('payments', [
            'company_id' => $this->company->id,
            'transaction_reference' => 'dummy-zapier-payment',
        ]);
    }

    public function testValidPayment()
    {
        $data = [
            'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);


        $response->assertStatus(200);
    }

    public function testValidPaymentWithAmount()
    {
        $data = [
            'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $this->credit->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);
    }

    public function testValidPaymentWithInvalidData()
    {
        $data = [
            'amount' => 0,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $this->credit->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(422);
    }
}
