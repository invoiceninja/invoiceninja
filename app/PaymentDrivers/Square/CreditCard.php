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

namespace App\PaymentDrivers\Square;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\SquarePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Square\Http\ApiResponse;
use Square\Models\CustomerDetails;
use Throwable;

class CreditCard implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    public function __construct(public SquarePaymentDriver $square_driver)
    {
        $this->square_driver->init();
    }

    /**
     * Authorization page for credit card.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView($data): View
    {
        $data['gateway'] = $this->square_driver;
        $data['square_contact'] = $this->buildClientObject();
        $data['currencyCode'] = $this->square_driver->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;

        return render('gateways.square.credit_card.authorize', $data);
    }

    /**
     * Handle authorization for credit card.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        $source_id = $request->input('sourceId');

        if (! is_string($source_id) || $source_id === '') {
            return redirect()->route('client.payment_methods.index')
                ->withErrors(ctrans('texts.invalid_card_number'));
        }

        $this->createCard($source_id);

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView($data): View
    {
        $data = $this->paymentData($data);

        return render('gateways.square.credit_card.pay', $data);
    }

    private function buildClientObject(): array
    {
        $client = new \stdClass();

        $country = $this->square_driver->client->country ? $this->square_driver->client->country->iso_3166_2 : $this->square_driver->client->company->country()->iso_3166_2;

        $client->addressLines = [$this->square_driver->client->address1 ?: '', $this->square_driver->client->address2 ?: ''];
        $client->givenName = $this->square_driver->client->present()->first_name();
        $client->familyName = $this->square_driver->client->present()->last_name();
        $client->email = $this->square_driver->client->present()->email();
        $client->phone = $this->square_driver->client->phone;
        $client->city = $this->square_driver->client->city;
        $client->state = $this->square_driver->client->state;
        $client->countryCode = $country;
        $client->postalCode = $this->square_driver->client->postal_code;

        return (array) $client;
    }

    public function paymentResponse(PaymentResponseRequest $request): RedirectResponse
    {
        $token = $request->sourceId;
        $idempotency_key = $request->input('idempotencyKey');

        if (! is_string($token) || $token === '') {
            throw new PaymentFailed(ctrans('texts.invalid_card_number'), 422);
        }

        if (! is_string($idempotency_key) || $idempotency_key === '' || strlen($idempotency_key) > 45) {
            throw new PaymentFailed(ctrans('texts.payment_error'), 422);
        }

        $amount = $this->square_driver->convertAmount(
            $this->square_driver->payment_hash->data->amount_with_fee
        );

        if ($request->shouldUseToken()) {
            $stored_card_id = $request->input('token');

            if (! is_string($stored_card_id) || $stored_card_id === '') {
                throw new PaymentFailed(ctrans('texts.invalid_card_number'), 422);
            }

            $cgt = ClientGatewayToken::query()
                ->where('token', $stored_card_id)
                ->where('client_id', $this->square_driver->client->id)
                ->where('company_gateway_id', $this->square_driver->company_gateway->id)
                ->where('gateway_type_id', GatewayType::CREDIT_CARD)
                ->where('is_deleted', false)
                ->first();

            if (! $cgt) {
                throw new PaymentFailed(ctrans('texts.invalid_card_number'), 422);
            }

            if (! is_string($cgt->gateway_customer_reference) || $cgt->gateway_customer_reference === '') {
                throw new PaymentFailed(ctrans('texts.payment_error'), 422);
            }
        }

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->square_driver->payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->square_driver->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->square_driver->client->present()->name()}";
        }

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($amount);
        $amount_money->setCurrency($this->square_driver->client->currency()->code);

        $body = new \Square\Models\CreatePaymentRequest($token, $idempotency_key);
        $body->setAmountMoney($amount_money);
        $body->setAutocomplete(true);
        $body->setLocationId($this->square_driver->company_gateway->getConfigField('locationId'));
        $body->setReferenceId($this->square_driver->payment_hash->hash);
        $body->setNote($description);
        $body->setCustomerDetails($this->customerDetails());

        if ($request->shouldUseToken()) {
            $body->setCustomerId($cgt->gateway_customer_reference);
        }

        if ($request->has('verificationToken') && $request->input('verificationToken')) {
            $body->setVerificationToken($request->input('verificationToken'));
        }

        $response = $this->square_driver->square->getPaymentsApi()->createPayment($body);

        if ($response->isSuccess()) {

            $body = json_decode($response->getBody());

            $payment = $this->processSuccessfulPayment($response);

            $card_storage_failed = false;

            if ($request->shouldStoreToken() && ! $request->shouldUseToken()) {
                try {
                    $this->createCard($body->payment->id);
                } catch (Throwable $e) {
                    $card_storage_failed = true;
                    $this->logCardStorageFailure($e);
                }
            }

            $redirect = redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

            return $card_storage_failed
                ? $redirect->withErrors(ctrans('texts.payment_method_saving_failed'))
                : $redirect;
        }

        if (is_array($response)) {
            nlog("square");
            nlog($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    private function processSuccessfulPayment(ApiResponse $response): Payment
    {
        $body = json_decode($response->getBody());

        $amount = array_sum(array_column($this->square_driver->payment_hash->invoices(), 'amount')) + $this->square_driver->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $body->payment->id;

        $payment = $this->square_driver->createPayment($payment_record, Payment::STATUS_COMPLETED);

        $message = [
            'server_response' => $body,
            'data' => $this->square_driver->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_SQUARE,
            $this->square_driver->client,
            $this->square_driver->client->company,
        );

        return $payment;
    }

    private function processUnsuccessfulPayment(ApiResponse $response): mixed
    {
        $body = \json_decode($response->getBody());

        $error = $body->errors[0]->detail
            ?? ($response->getBody() ?: 'Unknown error from Square.');

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $body->errors[0]->code ?? '',
        ];

        return $this->square_driver->processUnsuccessfulTransaction($data);
    }

    private function createCard(string $source_id): void
    {
        $square_card = new \Square\Models\Card();
        $square_card->setCustomerId($this->square_driver->findOrCreateClient());

        $body = new \Square\Models\CreateCardRequest(uniqid("st", true), $source_id, $square_card);

        $api_response = $this->square_driver
                             ->init()
                             ->square
                             ->getCardsApi()
                             ->createCard($body);

        $body = json_decode($api_response->getBody());

        if ($api_response->isSuccess()) {

            try {
                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) $body->card->exp_month;
                $payment_meta->exp_year = (string) $body->card->exp_year;
                $payment_meta->brand = (string) $body->card->card_brand;
                $payment_meta->last4 = (string) $body->card->last_4;
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $body->card->id,
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ];

                $this->square_driver->storeGatewayToken($data, ['gateway_customer_reference' => $body->card->customer_id]);

            } catch (Throwable $e) {
                throw new PaymentFailed(ctrans('texts.payment_method_saving_failed'), 500, $e);
            }

        } else {
            $message = $body->errors[0]->detail
                ?? ($api_response->getBody() ?: 'Unknown error from Square card creation.');

            throw new PaymentFailed($message, 500);
        }
    }

    private function customerDetails(): CustomerDetails
    {
        $customer_details = new CustomerDetails();
        $customer_details->setCustomerInitiated(true);
        $customer_details->setSellerKeyedIn(false);

        return $customer_details;
    }

    private function logCardStorageFailure(Throwable $e): void
    {
        SystemLogger::dispatch(
            [
                'error' => $e->getMessage(),
                'data' => $this->square_driver->payment_hash->data,
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_SQUARE,
            $this->square_driver->client,
            $this->square_driver->client->company,
        );
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.square.credit_card.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->square_driver;
        $data['amount'] = $this->square_driver->payment_hash->data->amount_with_fee;
        $data['currencyCode'] = $this->square_driver->client->getCurrencyCode();
        $data['square_contact'] = $this->buildClientObject();

        return $data;
    }
}
