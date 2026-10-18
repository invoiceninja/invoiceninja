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

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Country;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\GoCardless\HostedPaymentPage;
use App\PaymentDrivers\GoCardless\Jobs\GoCardlessWebhook;
use App\Utils\BcMath;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\QueryException;

class GoCardlessPaymentDriver extends BaseDriver
{
    use MakesHash;
    use GeneratesCounter;

    public $refundable = false;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public \GoCardlessPro\Client $gateway;

    public $payment_method;

    private bool $completed = true;

    public static $methods = [
        GatewayType::BANK_TRANSFER => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::DIRECT_DEBIT => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::SEPA => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::INSTANT_BANK_PAY => \App\PaymentDrivers\GoCardless\InstantBankPay::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_GOCARDLESS;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function ensurePaymentMethodAvailable(int $gateway_type_id, float $amount = -1): self
    {
        $is_available = collect($this->client->service()->getPaymentMethods($amount))
            ->contains(fn(array $method): bool
                => (int) $method['company_gateway_id'] === (int) $this->company_gateway->id
                && (int) $method['gateway_type_id'] === $gateway_type_id);

        if (! $is_available) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $this;
    }

    public function resolveClientGatewayToken(string $mandate_id, int $gateway_type_id): ClientGatewayToken
    {
        $token = $this->resolveOwnedClientGatewayToken($mandate_id);

        if ((int) $token->gateway_type_id !== $gateway_type_id) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $token;
    }

    public function resolveOwnedClientGatewayToken(string $mandate_id): ClientGatewayToken
    {
        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('client_id', $this->client->id)
            ->where('token', $mandate_id)
            ->first();

        if (! $token) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $token;
    }

    public function gatewayTypes(): array
    {
        if (! $this->client || ! isset($this->client->country)) {
            return [];
        }

        $types = [];
        $country = $this->client->country->iso_3166_2;
        $currency = $this->client->currency()->code;

        if ($country === 'US' && $currency === 'USD') {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        $direct_debit_countries = [
            'AUD' => 'AU',
            'CAD' => 'CA',
            'DKK' => 'DK',
            'GBP' => 'GB',
            'NZD' => 'NZ',
            'SEK' => 'SE',
        ];
        $supports_sepa = $currency === 'EUR' && in_array($country, HostedPaymentPage::SEPA_COUNTRIES, true);

        if (($direct_debit_countries[$currency] ?? null) === $country) {
            $types[] = GatewayType::DIRECT_DEBIT;
        }

        if ($supports_sepa) {
            $types[] = GatewayType::SEPA;
        }

        if (($currency === 'GBP' && $country === 'GB')
            || ($currency === 'EUR' && in_array($country, ['IE', 'FR', 'DE'], true))) {
            $types[] = GatewayType::INSTANT_BANK_PAY;
        }

        return $types;
    }

    public function init(): self
    {
        $environment = $this->company_gateway->getConfigField('testMode') ? \GoCardlessPro\Environment::SANDBOX : \GoCardlessPro\Environment::LIVE;

        if ($this->company_gateway->getConfigField('oauth2')) {
            $environment = \GoCardlessPro\Environment::LIVE;
        }

        try {
            $this->gateway = new \GoCardlessPro\Client([
                'access_token' => $this->company_gateway->getConfigField('accessToken'),
                'environment'  => $environment,
            ]);
        } catch (\GoCardlessPro\Core\Exception\AuthenticationException $e) {

            throw new \Exception('GoCardless: Invalid Access Token', 403);

        }

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        // ..
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $cgt = $this->resolveOwnedClientGatewayToken($cgt->token);
        $converted_amount = $this->convertToGoCardlessAmount($amount, $this->client->currency()->precision);

        $this->init();

        if ($payment_hash->fee_invoice) {
            $description = "Invoice {$payment_hash->fee_invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Amount {$amount} from client {$this->client->present()->name()}";
        }

        try {
            $payment = $this->gateway->payments()->create([
                'params' => [
                    'amount' => $converted_amount,
                    'currency' => $this->client->getCurrencyCode(),
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $cgt->token,
                    ],
                ],
            ]);

            if (in_array($payment->status, ['submitted', 'pending_submission', 'confirmed', 'paid_out'], true)) {

                $data = [
                    'payment_method' => $cgt->hashed_id,
                    'payment_type' => HostedPaymentPage::paymentTypeForStoredMandate(
                        $cgt->gateway_type_id,
                        $this->client->getCurrencyCode(),
                        data_get($cgt->meta, 'scheme'),
                    ),
                    'amount' => $amount,
                    'transaction_reference' => $payment->id,
                    'gateway_type_id' => $cgt->gateway_type_id,
                ];

                $this->confirmGatewayFee($data);

                $status = in_array($payment->status, ['confirmed', 'paid_out'], true)
                    ? Payment::STATUS_COMPLETED
                    : Payment::STATUS_PENDING;
                $_payment = $this->createPayment($data, $status);

                SystemLogger::dispatch(
                    ['response' => $payment, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_GOCARDLESS,
                    $this->client,
                    $this->client->company
                );

                return $_payment;
            }

            $this->sendFailureMail($payment->status);

            $message = [
                'server_response' => $payment,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );

            return false;
        } catch (\Exception $exception) {

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $exception->getCode(),
                'param' => '',
                'message' => $exception->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_GOCARDLESS, $this->client, $this->client->company);
        }
    }

    public function convertToGoCardlessAmount($amount, $precision): int
    {
        return BcMath::toMinorUnits($amount, (int) $precision);
    }

    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $this->gateway->mandates()->cancel($token->token);
        } catch (\Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                    'server_response' => $e->getMessage(),
                    'data' => request()->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Allow app to catch up with webhook request.
        $this->init();

        nlog('GoCardless Event');

        $webhook_secret = $this->company_gateway->getConfigField('webhookSecret');

        if ($webhook_secret) {
            $sig_header = $request->header('Webhook-Signature');

            if (! $sig_header) {
                return response()->json(['error' => 'No signature header'], 403);
            }

            try {
                \GoCardlessPro\Webhook::parse(
                    $request->getContent(),
                    $sig_header,
                    $webhook_secret
                );
            } catch (\GoCardlessPro\Core\Exception\InvalidSignatureException $e) {
                nlog('GoCardless webhook signature verification failed: ' . $e->getMessage());

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        if (! $request->has('events')) {
            nlog('No GoCardless events to process in response?');

            return response()->json([], 200);
        }

        GoCardlessWebhook::dispatch($request->events, $request->company_key, $this->decodePrimaryKey($request->company_gateway_id))->delay(2);

        return response()->json([], 200);
    }


    public function ensureMandateIsReady($token)
    {
        try {
            $this->init();
            $mandate = $this->gateway->mandates()->get($token);

            if (!in_array($mandate->status, ['pending_submission', 'submitted', 'active','pending_customer_approval'])) {

                // if ($mandate->status !== 'active') {
                throw new \Exception(ctrans('texts.gocardless_mandate_not_ready'));
            }
        } catch (\Exception $exception) {
            throw new \App\Exceptions\PaymentFailed($exception->getMessage());
        }
    }

    public function importCustomers()
    {
        $this->init();

        $customers = $this->gateway->customers()->list();

        foreach ($customers->records as $customer) {
            $existing_customer_token = $this->company_gateway
                            ->client_gateway_tokens()
                            ->where('gateway_customer_reference', $customer->id)
                            ->first();

            if ($existing_customer_token) {
                nlog("Skipping - Customer exists: {$customer->email} just updating payment methods");
                $this->updatePaymentMethods($customer, $existing_customer_token->client);
            } elseif ($customer->email && $this->company_gateway->company->client_contacts()->where('email', $customer->email)->exists()) {
                nlog("Customer exists: {$customer->email} just updating payment methods");

                $this->company_gateway->company->client_contacts()->where('email', $customer->email)->each(function ($contact) use ($customer) {
                    $this->updatePaymentMethods($customer, $contact->client);
                });
            } else {
                nlog("Creating new customer: {$customer->email}");
                $client = $this->createNinjaClient($customer);
                $this->updatePaymentMethods($customer, $client);
            }
        }
    }

    private function updatePaymentMethods($customer, Client $client): void
    {
        $this->client = $client;

        $mandates = $this->gateway->mandates()->list();

        foreach ($mandates->records as $mandate) {
            if ($customer->id != $mandate->links->customer || !in_array($mandate->status, ['active', 'pending_submission']) || ClientGatewayToken::where('token', $mandate->id)->where('gateway_customer_reference', $customer->id)->exists()) {
                // if ($customer->id != $mandate->links->customer || $mandate->status != 'active' || ClientGatewayToken::where('token', $mandate->id)->where('gateway_customer_reference', $customer->id)->exists()) {
                continue;
            }

            $payment_meta = new \stdClass();

            if ($mandate->scheme == 'bacs') {
                $payment_meta->brand = ctrans('texts.payment_type_direct_debit');
                $payment_meta->type = GatewayType::DIRECT_DEBIT;
            } elseif ($mandate->scheme == 'sepa_core') {
                $payment_meta->brand = ctrans('texts.sepa');
                $payment_meta->type = GatewayType::SEPA;
            } else {
                continue;
            }

            $payment_meta->state = 'authorized';
            $payment_meta->scheme = $mandate->scheme;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $mandate->id,
                'payment_method_id' => $payment_meta->type,
            ];

            $this->storeGatewayToken($data, ['gateway_customer_reference' => $mandate->links->customer]);
        }
    }

    /*
            [id] => CU0021PHBG5J6G
            [created_at] => 2022-12-02T11:24:15.739Z
            [email] => test@test.com
            [given_name] => Test
            [family_name] => Red
            [company_name] =>
            [address_line1] =>
            [address_line2] =>
            [address_line3] =>
            [city] =>
            [region] =>
            [postal_code] =>
            [country_code] =>
            [language] => en
            [swedish_identity_number] =>
            [danish_identity_number] =>
            [phone_number] =>
    */
    private function createNinjaClient($customer): Client
    {
        $client = ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);

        $client->address1 = $customer->address_line1 ?: '';
        $client->address2 = $customer->address_line2 ?: '';
        $client->city = $customer->city ?: '';
        $client->state = $customer->region ?: '';
        $client->postal_code = $customer->postal_code ?: '';
        $client->phone = $customer->phone_number ? $customer->phone_number : '';
        $client->name = $customer->company_name;

        if ($customer->country_code) {
            $country = Country::where('iso_3166_2', $customer->country_code)->first();

            if ($country) {
                $client->country_id = $country->id;
            } else {
                $client->country_id = $this->company_gateway->company->settings->country_id;
            }
        }

        $settings = $client->settings;
        $settings->currency_id = (string) $this->company_gateway->company->settings->currency_id;
        $client->settings = $settings;
        $client->save();

        $contact = ClientContactFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);
        $contact->first_name = $customer->given_name ?: '';
        $contact->last_name = $customer->family_name ?: '';
        $contact->email = $customer->email ?: '';
        $contact->client_id = $client->id;
        $contact->saveQuietly();

        if (! isset($client->number) || empty($client->number)) {
            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        } else {
            $client->saveQuietly();
        }

        return $client;
    }

    public function verificationView()
    {
        return render('gateways.gocardless.verification');
    }


    public function auth(): string
    {
        try {
            $customers = $this->init()->gateway->customers()->list();
            return 'ok';
        } catch (\Exception $e) {

        }

        return 'error';
    }
}
