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

namespace Tests\Unit\PaymentDrivers\Revolut;

use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Saved cards and merchant-initiated charges (auto-bill) of the Revolut
 * driver. Revolut is never called: Http is faked.
 */
class RevolutCreditCardTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private RevolutPaymentDriver $driver;

    private CompanyGateway $company_gateway;

    private PaymentHash $payment_hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->buildDriver(50.00);
    }

    public function testTokenBillingChargesTheSavedCardAsAMerchantInitiatedPayment(): void
    {
        $cgt = $this->savedCard();

        Http::fake([
            '*/api/orders/mit_order/payments' => Http::response(['id' => 'payment_1', 'state' => 'authorisation_passed']),
            '*/api/orders/mit_order' => Http::response($this->order('mit_order', 'completed')),
            '*/api/orders' => Http::response($this->order('mit_order', 'pending')),
        ]);

        $payment = $this->driver->tokenBilling($cgt, $this->payment_hash);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame('mit_order', $payment->transaction_reference);
        $this->assertSame(PaymentType::VISA, (int) $payment->type_id);
        $this->assertSame(GatewayType::CREDIT_CARD, (int) $payment->gateway_type_id);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders')
            && $request['customer']['id'] === 'cus_1'
            && $request['amount'] === 5000);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders/mit_order/payments')
            && $request['saved_payment_method'] === ['type' => 'card', 'id' => 'pm_1', 'initiator' => 'merchant']);
    }

    public function testDeclinedChargeThrowsSoAutoBillCountsTheFailure(): void
    {
        $cgt = $this->savedCard();

        Http::fake([
            '*/api/orders/mit_order/payments' => Http::response(['code' => 'payment_declined', 'message' => 'Insufficient funds'], 422),
            '*/api/orders' => Http::response($this->order('mit_order', 'pending')),
        ]);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('Insufficient funds');

        $this->driver->tokenBilling($cgt, $this->payment_hash);
    }

    public function testAnotherClientsTokenIsNeverCharged(): void
    {
        $cgt = $this->savedCard();
        $cgt->client_id = $this->client->id + 1000;

        Http::fake();

        try {
            $this->driver->tokenBilling($cgt, $this->payment_hash);
            $this->fail('Expected PaymentFailed');
        } catch (PaymentFailed) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'revolut.com'));
        }
    }

    public function testCardIsSavedOnlyOnceRevolutConfirmsItIsSavedForTheMerchant(): void
    {
        Http::fake([
            '*/api/customers/cus_1/payment-methods' => Http::sequence()
                ->push(['payment_methods' => [['id' => 'pm_new', 'type' => 'card', 'saved_for' => 'customer']]])
                ->push(['payment_methods' => [['id' => 'pm_new', 'type' => 'card', 'saved_for' => 'merchant']]]),
        ]);

        $order = $this->order('order_1', 'completed', 'pm_new');
        $order['payments'][0]['payment_method']['fingerprint'] = 'fp_new';

        $this->driver->storeSavedPaymentMethod($order, GatewayType::CREDIT_CARD);
        $this->assertSame(0, ClientGatewayToken::where('token', 'pm_new')->count());

        $this->driver->storeSavedPaymentMethod($order, GatewayType::CREDIT_CARD);
        $this->driver->storeSavedPaymentMethod($order, GatewayType::CREDIT_CARD);

        $tokens = ClientGatewayToken::where('token', 'pm_new')->get();
        $this->assertCount(1, $tokens);
        $this->assertSame('cus_1', $tokens->first()->gateway_customer_reference);
        $this->assertSame('Visa', $tokens->first()->meta->brand);
        $this->assertSame('5709', $tokens->first()->meta->last4);
        $this->assertSame('12', $tokens->first()->meta->exp_month);
        $this->assertSame('2030', $tokens->first()->meta->exp_year);
        $this->assertSame('fp_new', $tokens->first()->meta->fingerprint);
    }

    public function testTheSameCardEnteredAgainIsNotSavedTwice(): void
    {
        $saved = $this->savedCard();
        $saved->meta = (object) array_merge((array) $saved->meta, ['fingerprint' => 'fp_visa']);
        $saved->save();

        Http::fake(['*/api/customers/cus_1/payment-methods/pm_again' => Http::response(null, 204)]);

        $order = $this->order('order_1', 'completed', 'pm_again');
        $order['payments'][0]['payment_method']['fingerprint'] = 'fp_visa';

        $token = $this->driver->storeSavedPaymentMethod($order, GatewayType::CREDIT_CARD);

        $this->assertSame($saved->id, $token->id);
        $this->assertSame(0, ClientGatewayToken::where('token', 'pm_again')->count());
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/customers/cus_1/payment-methods/pm_again'));
    }

    public function testTheClientsExistingRevolutCustomerIsReused(): void
    {
        $this->savedCard();

        Http::fake();

        $this->assertSame('cus_1', $this->driver->revolutCustomerId());
        Http::assertNothingSent();
    }

    public function testARevolutCustomerIsCreatedForAClientWithoutOne(): void
    {
        Http::fake(['*/api/customers' => Http::response(['id' => 'cus_new'], 201)]);

        $this->assertSame('cus_new', $this->driver->revolutCustomerId());
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/customers') && ! empty($request['email']));
    }

    public function testRemovingASavedCardDeletesItAtRevolut(): void
    {
        $cgt = $this->savedCard();

        Http::fake(['*' => Http::response(null, 204)]);

        $this->assertTrue($this->driver->detach($cgt));
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/api/customers/cus_1/payment-methods/pm_1'));
    }

    public function testPayingWithASavedCardInThePortalChargesItAsAMerchantInitiatedPaymentOnThePageOrder(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('order_id', 'page_order');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        Http::fake([
            '*/api/orders/page_order/payments' => Http::response(['id' => 'payment_1', 'state' => 'pending']),
            '*/api/orders/page_order' => Http::sequence()
                ->push($this->order('page_order', 'pending'))
                ->push($this->order('page_order', 'pending'))
                ->push($this->order('page_order', 'completed')),
        ]);

        $response = $this->driver->payWithSavedMethod($cgt);

        $this->assertStringContainsString('/client/payments/', $response->getTargetUrl());
        $this->assertSame(Payment::STATUS_COMPLETED, Payment::where('transaction_reference', 'page_order')->value('status_id'));

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request['customer']['id'] === 'cus_1');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders/page_order/payments')
            && $request['saved_payment_method'] === ['type' => 'card', 'id' => 'pm_1', 'initiator' => 'merchant']);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/orders'));
    }

    public function testAPortalPaymentWithADeclinedSavedCardFails(): void
    {
        $cgt = $this->savedCard();

        Http::fake([
            '*/api/orders/new_order/payments' => Http::response(['code' => 'payment_declined', 'message' => 'Do not honour'], 422),
            '*/api/orders' => Http::response($this->order('new_order', 'pending')),
        ]);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('Do not honour');

        $this->driver->payWithSavedMethod($cgt);
    }

    public function testARetriedSavedCardPaymentIsNotChargedAgain(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('order_id', 'page_order');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        $charges = 0;

        Http::fake(function (Request $request) use (&$charges) {
            if (str_ends_with($request->url(), '/api/orders/page_order/payments')) {
                $charges++;

                return Http::response(['id' => 'payment_1', 'state' => 'authorisation_started']);
            }

            return Http::response($this->order('page_order', $charges > 0 ? 'completed' : 'pending'));
        });

        $this->driver->payWithSavedMethod($cgt);
        $this->driver->payWithSavedMethod($cgt);

        $this->assertSame(1, $charges);
        $this->assertSame(1, Payment::where('transaction_reference', 'page_order')->count());
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/orders'));
    }

    public function testAnOrderWithAPaymentInProgressIsNotChargedAgain(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('order_id', 'page_order');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        $in_progress = array_merge($this->order('page_order', 'pending'), ['payments' => [['state' => 'authorisation_started']]]);

        Http::fake(['*/api/orders/page_order' => Http::sequence()->push($in_progress)->push($this->order('page_order', 'completed'))]);

        $this->driver->payWithSavedMethod($cgt);

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/payments') || $request->method() === 'PATCH');
        $this->assertSame(Payment::STATUS_COMPLETED, Payment::where('transaction_reference', 'page_order')->value('status_id'));
    }

    public function testASavedCardIsNotChargedWhenTheExistingOrderCannotBeRead(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('order_id', 'page_order');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        Http::fake(['*/api/orders/page_order' => Http::response(['message' => 'Service unavailable'], 503)]);

        try {
            $this->driver->payWithSavedMethod($cgt);
            $this->fail('Expected PaymentFailed');
        } catch (PaymentFailed) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'revolut.com') && $request->method() !== 'GET');
            $this->assertSame('page_order', $this->payment_hash->fresh()->data->order_id);
        }
    }

    public function testAWaitingRequestUsesTheOrderTheOtherRequestCharged(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);
        $this->payment_hash->withData('order_id', 'failed_order');

        // While this request waited, another one replaced the failed order and charged the new one.
        $data = (array) $this->payment_hash->data;
        $data['order_id'] = 'new_order';
        PaymentHash::where('id', $this->payment_hash->id)->update(['data' => json_encode($data)]);

        Http::fake([
            '*/api/orders/failed_order' => Http::response(array_merge($this->order('failed_order', 'failed'), ['payments' => [['state' => 'declined']]])),
            '*/api/orders/new_order' => Http::response($this->order('new_order', 'completed')),
        ]);

        $this->driver->payWithSavedMethod($cgt);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'revolut.com') && $request->method() !== 'GET');
        $this->assertSame(Payment::STATUS_COMPLETED, Payment::where('transaction_reference', 'new_order')->value('status_id'));
    }

    public function testASavedCardCanBeChargedAgainAfterADecline(): void
    {
        $cgt = $this->savedCard();
        $this->payment_hash->withData('order_id', 'page_order');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        $declined = array_merge($this->order('page_order', 'pending'), ['payments' => [['state' => 'declined', 'decline_reason' => 'insufficient_funds']]]);

        Http::fake([
            '*/api/orders/page_order/payments' => Http::response(['id' => 'payment_2', 'state' => 'authorisation_started']),
            '*/api/orders/page_order' => Http::sequence()->push($declined)->push($declined)->push($this->order('page_order', 'completed')),
        ]);

        $this->driver->payWithSavedMethod($cgt);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders/page_order/payments'));
        $this->assertSame(Payment::STATUS_COMPLETED, Payment::where('transaction_reference', 'page_order')->value('status_id'));
    }

    public function testTheCardIsSavedWhenTheWebhookRecordsThePaymentFirst(): void
    {
        $this->payment_hash->withData('order_id', 'order_1');
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        Http::fake([
            '*/api/customers/cus_1/payment-methods' => Http::response(['payment_methods' => [['id' => 'pm_new', 'type' => 'card', 'saved_for' => 'merchant']]]),
            '*/api/orders/order_1' => Http::response($this->order('order_1', 'completed', 'pm_new')),
        ]);

        // The webhook records the order before the customer is back in the portal.
        $this->driver->recordOrder($this->order('order_1', 'completed', 'pm_new'));

        $this->assertSame(1, ClientGatewayToken::where('token', 'pm_new')->where('client_id', $this->client->id)->count());

        $this->driver->completeCustomerPayment();

        $this->assertSame(1, ClientGatewayToken::where('token', 'pm_new')->count());
    }

    public function testACardTheClientDidNotSaveIsNotStored(): void
    {
        $this->payment_hash->withData('gateway_type_id', GatewayType::CREDIT_CARD);

        Http::fake([
            '*/api/customers/cus_1/payment-methods' => Http::response(['payment_methods' => []]),
        ]);

        $this->driver->recordOrder($this->order('order_1', 'completed', 'pm_unsaved'));

        $this->assertSame(1, Payment::where('transaction_reference', 'order_1')->count());
        $this->assertSame(0, ClientGatewayToken::where('token', 'pm_unsaved')->count());
    }

    public function testOnlyTheClientsOwnSavedCardsCanBeUsed(): void
    {
        $cgt = $this->savedCard();

        $this->assertSame($cgt->id, $this->driver->savedPaymentMethod($cgt->hashed_id, GatewayType::CREDIT_CARD)?->id);
        $this->assertNull($this->driver->savedPaymentMethod($cgt->hashed_id, GatewayType::HOSTED_PAGE));

        $cgt->client_id = \App\Models\Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id])->id;
        $cgt->save();

        $this->assertNull($this->driver->savedPaymentMethod($cgt->hashed_id, GatewayType::CREDIT_CARD));
    }

    public function testAddingACardWithoutPayingUsesAZeroAmountOrder(): void
    {
        $this->savedCard();

        Http::fake([
            '*/api/orders/setup_order' => Http::response(array_merge($this->order('setup_order', 'completed', 'pm_added'), ['amount' => 0])),
            '*/api/orders' => Http::response(['id' => 'setup_order', 'token' => 'public_setup', 'state' => 'pending', 'amount' => 0]),
            '*/api/customers/cus_1/payment-methods' => Http::response(['payment_methods' => [['id' => 'pm_added', 'type' => 'card', 'saved_for' => 'merchant']]]),
        ]);

        $this->driver->payment_hash = null;
        $view = $this->driver->payment_method->authorizeView([]);

        $this->assertSame('public_setup', $view->getData()['order_token']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders') && $request['amount'] === 0 && $request['customer']['id'] === 'cus_1');

        $this->driver->payment_method->authorizeResponse(new \Illuminate\Http\Request());

        $this->assertSame(1, ClientGatewayToken::where('token', 'pm_added')->where('client_id', $this->client->id)->count());
    }

    public function testAddingACardRejectsAnOrderThisSessionDidNotCreate(): void
    {
        $this->savedCard();

        Http::fake([
            '*/api/orders/setup_order' => Http::response(array_merge($this->order('setup_order', 'completed', 'pm_added'), ['amount' => 0, 'customer' => ['id' => 'someone_else']])),
        ]);

        session()->put('revolut_setup_order', ['order_id' => 'setup_order', 'customer_id' => 'cus_1']);

        $this->expectException(PaymentFailed::class);

        $this->driver->payment_method->authorizeResponse(new \Illuminate\Http\Request());
    }

    private function savedCard(): ClientGatewayToken
    {
        $cgt = new ClientGatewayToken();
        $cgt->company_id = $this->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->gateway_type_id = GatewayType::CREDIT_CARD;
        $cgt->token = 'pm_1';
        $cgt->gateway_customer_reference = 'cus_1';
        $cgt->meta = (object) ['brand' => 'Visa', 'last4' => '5709', 'exp_month' => '12', 'exp_year' => '2030'];
        $cgt->save();

        return $cgt;
    }

    private function order(string $id, string $state, string $method_id = 'pm_1'): array
    {
        return [
            'id' => $id,
            'token' => 'public_' . $id,
            'state' => $state,
            'amount' => 5000,
            'currency' => $this->client->getCurrencyCode(),
            'customer' => ['id' => 'cus_1'],
            'payments' => $state === 'completed' ? [[
                'state' => 'captured',
                'payment_method' => [
                    'id' => $method_id,
                    'type' => 'card',
                    'card_brand' => 'visa',
                    'card_last_four' => '5709',
                    'card_expiry' => '12/30',
                ],
            ]] : [],
        ];
    }

    private function buildDriver(float $amount_with_fee): void
    {
        $gateway = Gateway::where('provider', 'Revolut')->firstOrFail();

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = $gateway->key;
        $this->company_gateway->token_billing = 'optout';
        $this->company_gateway->setConfig(['apiKey' => 'sk_test', 'signingSecret' => 'wsk_test', 'testMode' => true]);
        $this->company_gateway->save();

        $this->payment_hash = PaymentHash::create([
            'company_id' => $this->company->id,
            'hash' => str()->random(32),
            'fee_invoice_id' => $this->invoice->id,
            'data' => json_decode(json_encode([
                'amount_with_fee' => $amount_with_fee,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $amount_with_fee,
                    'invoice_number' => $this->invoice->number,
                ]],
            ])),
        ]);

        $this->driver = new RevolutPaymentDriver($this->company_gateway, $this->client, null);
        $this->driver->setPaymentHash($this->payment_hash);
        $this->driver->setPaymentMethod(GatewayType::CREDIT_CARD);
    }
}
