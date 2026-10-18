<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\PaymentDrivers\Square;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\Square\CreditCard;
use App\PaymentDrivers\SquarePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Square\Http\ApiResponse;
use Square\Models\CreateCardRequest;
use Square\Models\CreatePaymentRequest;
use Tests\MockAccountData;
use Tests\TestCase;

class SquareCreditCardTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        Bus::fake();
    }

    public function testNewCardPaymentStoresCardFromSuccessfulPayment(): void
    {
        $square = $this->squareClient();
        $driver = $this->driver($square);
        $driver->shouldReceive('findOrCreateClient')->once()->andReturn('square-customer-id');
        $driver->shouldReceive('storeGatewayToken')->once()->andReturn(new ClientGatewayToken());

        $response = (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-source-token',
            'store_card' => true,
        ]));

        $this->assertTrue($response->isRedirect());
        $this->assertSame('square-source-token', $square->payments_api->request->getSourceId());
        $this->assertNull($square->payments_api->request->getVerificationToken());
        $this->assertTrue($square->payments_api->request->getCustomerDetails()->getCustomerInitiated());
        $this->assertFalse($square->payments_api->request->getCustomerDetails()->getSellerKeyedIn());
        $this->assertSame('square-payment-id', $square->cards_api->request->getSourceId());
    }

    public function testNewCardPaymentDoesNotStoreCardWhenNotRequested(): void
    {
        $square = $this->squareClient();
        $driver = $this->driver($square);
        $driver->shouldNotReceive('findOrCreateClient');
        $driver->shouldNotReceive('storeGatewayToken');

        (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-source-token',
            'store_card' => false,
        ]));

        $this->assertNull($square->cards_api->request);
    }

    public function testStoredTokenPaymentNeverAttemptsToStoreAnotherCard(): void
    {
        $gateway = $this->gateway();

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $gateway->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->token = 'stored-square-card';
        $token->gateway_customer_reference = 'square-customer-id';
        $token->save();

        $square = $this->squareClient();
        $driver = $this->driver($square, $gateway);
        $driver->shouldNotReceive('findOrCreateClient');
        $driver->shouldNotReceive('storeGatewayToken');

        (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-verified-card-token',
            'store_card' => true,
            'token' => $token->token,
        ]));

        $this->assertSame('square-verified-card-token', $square->payments_api->request->getSourceId());
        $this->assertSame('square-customer-id', $square->payments_api->request->getCustomerId());
        $this->assertTrue($square->payments_api->request->getCustomerDetails()->getCustomerInitiated());
        $this->assertFalse($square->payments_api->request->getCustomerDetails()->getSellerKeyedIn());
        $this->assertNull($square->cards_api->request);
    }

    public function testStoredTokenPaymentRequiresVerifiedSourceId(): void
    {
        $square = $this->squareClient();
        $driver = $this->driver($square);

        $this->expectException(PaymentFailed::class);

        (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => '',
            'store_card' => false,
            'token' => 'stored-square-card',
        ]));
    }

    public function testPaymentRequiresValidIdempotencyKey(): void
    {
        $square = $this->squareClient();
        $driver = $this->driver($square);

        $this->expectException(PaymentFailed::class);

        (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'idempotencyKey' => str_repeat('a', 46),
            'sourceId' => 'square-source-token',
            'store_card' => false,
        ]));
    }

    public function testStoredTokenMustBelongToSelectedGateway(): void
    {
        $selected_gateway = $this->gateway();
        $other_gateway = $this->gateway();

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $other_gateway->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->token = 'stored-square-card';
        $token->gateway_customer_reference = 'square-customer-id';
        $token->save();

        $square = $this->squareClient();
        $driver = $this->driver($square, $selected_gateway);

        $this->expectException(PaymentFailed::class);

        (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-verified-card-token',
            'store_card' => false,
            'token' => $token->token,
        ]));
    }

    public function testStandaloneAuthorizationStoresVerifiedCardToken(): void
    {
        $square = $this->squareClient();
        $driver = $this->driver($square);
        $driver->shouldReceive('findOrCreateClient')->once()->andReturn('square-customer-id');
        $driver->shouldReceive('storeGatewayToken')->once()->andReturn(new ClientGatewayToken());

        $response = (new CreditCard($driver))->authorizeResponse(Request::create('/', 'POST', [
            'sourceId' => 'square-store-token',
        ]));

        $this->assertTrue($response->isRedirect());
        $this->assertSame('square-store-token', $square->cards_api->request->getSourceId());
    }

    public function testSuccessfulPaymentIsNotFailedWhenCardStorageFails(): void
    {
        $square = $this->squareClient(false);
        $driver = $this->driver($square);
        $driver->shouldReceive('findOrCreateClient')->once()->andReturn('square-customer-id');

        $response = (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-source-token',
            'store_card' => true,
        ]));

        $this->assertTrue($response->isRedirect());
        $this->assertTrue($response->getSession()->get('errors')->getBag('default')->isNotEmpty());
        $this->assertSame('square-payment-id', $square->cards_api->request->getSourceId());
    }

    public function testAutoBillingMarksStoredCardChargeAsSellerInitiated(): void
    {
        $gateway = $this->gateway();
        $square = $this->squareClient();
        $driver = $this->driver($square, $gateway);

        $token = new ClientGatewayToken();
        $token->token = 'stored-square-card';
        $token->gateway_customer_reference = 'square-customer-id';

        $payment = $driver->tokenBilling($token, $driver->payment_hash);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('stored-square-card', $square->payments_api->request->getSourceId());
        $this->assertSame('square-customer-id', $square->payments_api->request->getCustomerId());
        $this->assertFalse($square->payments_api->request->getCustomerDetails()->getCustomerInitiated());
        $this->assertFalse($square->payments_api->request->getCustomerDetails()->getSellerKeyedIn());
        $this->assertSame('square-location-id', $square->payments_api->request->getLocationId());
        $this->assertTrue($square->payments_api->request->getAutocomplete());
    }

    public function testSquareDeclineCodeIsPreserved(): void
    {
        $square = $this->squareClient(true, false);
        $driver = $this->driver($square);
        $driver->shouldReceive('processUnsuccessfulTransaction')
            ->once()
            ->with(Mockery::on(fn(array $data): bool => $data['error_code'] === 'CARD_DECLINED_VERIFICATION_REQUIRED'))
            ->andReturn(redirect('/'));

        $response = (new CreditCard($driver))->paymentResponse($this->paymentRequest([
            'sourceId' => 'square-source-token',
            'store_card' => false,
        ]));

        $this->assertTrue($response->isRedirect());
    }

    public function testPaymentDataUsesCurrentSquareBillingContactKeys(): void
    {
        $this->client->state = 'NSW';
        $this->client->postal_code = '2000';
        $this->client->save();

        $driver = $this->driver($this->squareClient());
        $data = (new CreditCard($driver))->paymentData([]);

        $this->assertSame('NSW', $data['square_contact']['state']);
        $this->assertSame('2000', $data['square_contact']['postalCode']);
        $this->assertArrayHasKey('countryCode', $data['square_contact']);
        $this->assertArrayNotHasKey('region', $data['square_contact']);
        $this->assertArrayNotHasKey('country', $data['square_contact']);
    }

    private function paymentRequest(array $data): PaymentResponseRequest
    {
        return PaymentResponseRequest::create('/', 'POST', array_merge([
            'idempotencyKey' => 'square-idempotency-key',
            'token' => '',
        ], $data));
    }

    private function gateway(): CompanyGateway
    {
        $gateway = new CompanyGateway();
        $gateway->company_id = $this->company->id;
        $gateway->user_id = $this->user->id;
        $gateway->gateway_key = '65faab2ab6e3223dbe848b1686490baz';
        $gateway->config = encrypt(json_encode([
            'locationId' => 'square-location-id',
        ]));
        $gateway->save();

        return $gateway;
    }

    private function driver(object $square, ?CompanyGateway $gateway = null): SquarePaymentDriver
    {
        $payment_hash = new PaymentHash();
        $payment_hash->hash = 'square-payment-hash';
        $payment_hash->fee_total = 0;
        $payment_hash->data = [
            'amount_with_fee' => 10,
            'invoices' => [],
        ];

        $payment = new Payment();
        $payment->id = 1;

        $driver = Mockery::mock(SquarePaymentDriver::class, [$gateway ?? $this->gateway(), $this->client])
            ->makePartial();
        $driver->payment_hash = $payment_hash;
        $driver->square = $square;
        $driver->shouldReceive('init')->andReturnSelf();
        $driver->shouldReceive('convertAmount')->zeroOrMoreTimes()->andReturn(1000);
        $driver->shouldReceive('createPayment')->zeroOrMoreTimes()->andReturn($payment);

        return $driver;
    }

    private function squareClient(bool $card_success = true, bool $payment_success = true): object
    {
        $payment_response = $payment_success
            ? $this->successfulResponse([
                'payment' => [
                    'id' => 'square-payment-id',
                ],
            ])
            : $this->failedResponse([
                'errors' => [[
                    'code' => 'CARD_DECLINED_VERIFICATION_REQUIRED',
                    'detail' => 'Card verification is required.',
                ]],
            ]);
        $card_response = $card_success
            ? $this->successfulResponse([
                'card' => [
                    'id' => 'square-card-id',
                    'customer_id' => 'square-customer-id',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                    'card_brand' => 'VISA',
                    'last_4' => '1111',
                ],
            ])
            : $this->failedResponse([
                'errors' => [[
                    'code' => 'CARD_DECLINED_VERIFICATION_REQUIRED',
                    'detail' => 'Card verification is required.',
                ]],
            ]);

        $payments_api = new class ($payment_response) {
            public ?CreatePaymentRequest $request = null;

            public function __construct(private ApiResponse $response) {}

            public function createPayment(CreatePaymentRequest $request): ApiResponse
            {
                $this->request = $request;

                return $this->response;
            }
        };

        $cards_api = new class ($card_response) {
            public ?CreateCardRequest $request = null;

            public function __construct(private ApiResponse $response) {}

            public function createCard(CreateCardRequest $request): ApiResponse
            {
                $this->request = $request;

                return $this->response;
            }
        };

        return new class ($payments_api, $cards_api) {
            public function __construct(
                public object $payments_api,
                public object $cards_api,
            ) {}

            public function getPaymentsApi(): object
            {
                return $this->payments_api;
            }

            public function getCardsApi(): object
            {
                return $this->cards_api;
            }
        };
    }

    private function successfulResponse(array $body): ApiResponse
    {
        $response = Mockery::mock(ApiResponse::class);
        $response->shouldReceive('isSuccess')->andReturnTrue();
        $response->shouldReceive('getBody')->andReturn(json_encode($body, JSON_THROW_ON_ERROR));

        return $response;
    }

    private function failedResponse(array $body): ApiResponse
    {
        $response = Mockery::mock(ApiResponse::class);
        $response->shouldReceive('isSuccess')->andReturnFalse();
        $response->shouldReceive('getBody')->andReturn(json_encode($body, JSON_THROW_ON_ERROR));

        return $response;
    }
}
