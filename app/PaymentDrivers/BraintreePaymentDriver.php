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

use Exception;
use App\Models\Client;
use Braintree\Gateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use Illuminate\Support\Carbon;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Factory\ClientContactFactory;
use App\PaymentDrivers\Braintree\ACH;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;
use App\PaymentDrivers\Braintree\PayPal;
use Illuminate\Support\Facades\Validator;
use App\PaymentDrivers\Braintree\CreditCard;

class BraintreePaymentDriver extends BaseDriver
{
    use GeneratesCounter;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    private bool $completed = true;

    /**
     * @var Gateway;
     */
    public Gateway $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::PAYPAL => PayPal::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_BRAINTREE;

    public function init(): self
    {
        $this->gateway = new Gateway([
            'environment' => $this->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production',
            'merchantId' => $this->company_gateway->getConfigField('merchantId'),
            'publicKey' => $this->company_gateway->getConfigField('publicKey'),
            'privateKey' => $this->company_gateway->getConfigField('privateKey'),
        ]);

        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::PAYPAL,
            GatewayType::CREDIT_CARD,
            GatewayType::BANK_TRANSFER,
        ];

        return $types;
    }

    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function findOrCreateCustomer()
    {
        $existing = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('client_id', $this->client->id)
            ->first();

        if ($existing) {
            return $this->gateway->customer()->find($existing->gateway_customer_reference);
        }

        $customer = $this->searchByEmail();

        if ($customer) {
            return $customer;
        }

        $result = $this->gateway->customer()->create([
            'firstName' => $this->client->present()->name(),
            'email' => $this->client->present()->email(),
            'phone' => $this->client->present()->phone(),
        ]);

        if ($result->success) {
            $address = $this->gateway->address()->create([
                'customerId' => $result->customer->id,
                'firstName' => $this->client->present()->name(),
                'streetAddress' => $this->client->address1 ?: '',
                'postalCode' => $this->client->postal_code ?: '',
                'countryCodeAlpha2' => $this->client->country ? $this->client->country->iso_3166_2 : '',
            ]);

            return $result->customer;
        }
        //12-08-2022 catch when the customer is not created.
        $data = [
            'transaction_reference' => null,
            'transaction_response' => $result,
            'success' => false,
            'description' => 'Could not create customer',
            'code' => 500,
        ];

        SystemLogger::dispatch(['server_response' => $result, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);
    }

    private function searchByEmail()
    {
        $result = $this->gateway->customer()->search([
            \Braintree\CustomerSearch::email()->is($this->client->present()->email()),
        ]);

        if ($result->maximumCount() > 0) {
            return $result->firstItem();
        }
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        try {
            $response = $this->gateway->transaction()->refund($payment->transaction_reference, $amount);
        } catch (Exception $e) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

            SystemLogger::dispatch(['server_response' => null, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        }

        if ($response->success) {
            $data = [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => json_encode($response),
                'success' => (bool) $response->success,
                'description' => ctrans('texts.plan_refunded'),
                'code' => 0,
            ];

            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        } else {
            $error = $response->errors->deepAll()[0];

            $data = [
                'transaction_reference' => null,
                'transaction_response' => $response->errors->deepAll(),
                'success' => false,
                'description' => $error->message,
                'code' => $error->code,
            ];

            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();
        $total_taxes = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->sum('total_taxes');

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $this->init();

        $data = [
            'amount' => $amount,
            'paymentMethodToken' => $cgt->token,
            'deviceData' => '',
            'channel' => 'invoiceninja_BT',
            'options' => [
                'submitForSettlement' => true,
            ],
            'taxAmount' => $total_taxes,
            'purchaseOrderNumber' => substr($invoice->po_number ?? $invoice->number, 0, 16),
        ];

        $data = array_merge($data, $this->getLevel23Data($payment_hash));

        $result = $this->gateway->transaction()->sale($data);

        if ($result->success) {

            $data = [
                'payment_type' => PaymentType::parseCardType(strtolower($result->transaction->creditCard['cardType'])),
                'amount' => $amount,
                'transaction_reference' => $result->transaction->id,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $this->confirmGatewayFee($data);

            $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $result, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_BRAINTREE,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        if (! $result->success) {
            $this->unWindGatewayFees($payment_hash);

            $this->sendFailureMail($result->transaction->additionalProcessorResponse);

            $message = [
                'server_response' => $result,
                'data' => $this->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_BRAINTREE,
                $this->client,
                $this->client->company
            );

            return false;
        }
    }


    /**
     * Build Level 2/3 interchange data for Braintree transactions.
     *
     * Wrapped in try-catch — this is supplemental data for lower interchange rates
     * and must never cause a payment to fail.
     *
     * @param PaymentHash $payment_hash
     * @return array
     */
    public function getLevel23Data(PaymentHash $payment_hash): array
    {
        try {
            $data = [];

            $invoice_ids = $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id'));
            $invoices = Invoice::query()->whereIn('id', $invoice_ids)->withTrashed()->get();

            if ($invoices->isEmpty()) {
                return [];
            }

            // Level 2: Shipping address
            $client = $this->client;
            if ($client && $client->shipping_postal_code) {
                $data['shipping'] = [
                    'streetAddress' => $client->shipping_address1 ?: '',
                    'extendedAddress' => $client->shipping_address2 ?: '',
                    'locality' => $client->shipping_city ?: '',
                    'region' => $client->shipping_state ?: '',
                    'postalCode' => $client->shipping_postal_code ?: '',
                    'countryCodeAlpha3' => $client->shipping_country ? ($client->shipping_country->iso_3166_3 ?: '') : '',
                ];
            }

            // Level 2: Ships from postal code (company address)
            $company = $this->company_gateway->company;
            $company_postal_code = $company->settings->postal_code ?? '';
            if ($company_postal_code) {
                $data['shippingAmount'] = '0.00';
                $data['shipsFromPostalCode'] = substr($company_postal_code, 0, 10);
            }

            // Level 2: Discount amount across all invoices
            $total_discount = 0;
            foreach ($invoices as $invoice) {
                if ($invoice->discount > 0) {
                    if ($invoice->is_amount_discount) {
                        $total_discount += $invoice->discount;
                    } else {
                        // Percentage discount — calculate from subtotal
                        $subtotal = collect($invoice->line_items)->sum(function ($item) {
                            return (float) ($item->line_total ?? 0);
                        });
                        $total_discount += round($subtotal * ($invoice->discount / 100), 2);
                    }
                }
            }
            if ($total_discount > 0) {
                $data['discountAmount'] = number_format($total_discount, 2, '.', '');
            }

            // Level 3: Line items (max 249 per Braintree)
            $line_items = [];
            foreach ($invoices as $invoice) {
                $items = $invoice->line_items;
                if (!is_iterable($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (count($line_items) >= 249) {
                        break 2;
                    }

                    $quantity = (float) ($item->quantity ?? 1);
                    $unit_amount = (float) ($item->cost ?? 0);
                    $line_total = (float) ($item->line_total ?? ($quantity * $unit_amount));
                    $tax_amount = (float) ($item->tax_amount ?? 0);

                    // Calculate item-level discount
                    $item_discount = 0.0;
                    $raw_discount = (float) ($item->discount ?? 0);
                    if ($raw_discount > 0) {
                        $is_amount = $item->is_amount_discount ?? false;
                        $item_discount = $is_amount ? $raw_discount : round(($quantity * $unit_amount) * ($raw_discount / 100), 2);
                    }

                    $bt_item = [
                        'name' => substr($item->product_key ?? 'Item', 0, 35),
                        'kind' => \Braintree\TransactionLineItem::DEBIT,
                        'quantity' => number_format($quantity, 4, '.', ''),
                        'unitAmount' => number_format(abs($unit_amount), 4, '.', ''),
                        'totalAmount' => number_format(abs($line_total), 2, '.', ''),
                        'taxAmount' => number_format(abs($tax_amount), 2, '.', ''),
                        'discountAmount' => number_format(abs($item_discount), 2, '.', ''),
                        'unitOfMeasure' => 'EA',
                        'productCode' => substr($item->product_key ?? '', 0, 12),
                    ];

                    // Add description if available
                    $notes = $item->notes ?? '';
                    if ($notes !== '') {
                        $bt_item['description'] = substr($notes, 0, 127);
                    }

                    $line_items[] = $bt_item;
                }
            }

            if (!empty($line_items)) {
                $data['lineItems'] = $line_items;
            }

            return $data;

        } catch (\Exception $e) {
            nlog("Braintree L2/L3 data build failed (non-fatal): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Required fields for client to fill, to proceed with gateway actions.
     *
     * @return array[]
     */
    public function getClientRequiredFields(): array
    {
        $fields = [];

        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];

        return $fields;
    }

    public function processWebhookRequest($request)
    {

        $validator = Validator::make($request->all(), [
            'bt_signature' => ['required'],
            'bt_payload' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $this->init();

        $webhookNotification = $this->gateway->webhookNotification()->parse(
            $request->input('bt_signature'),
            $request->input('bt_payload')
        );

        nlog('braintree webhook');
        nlog($webhookNotification);

        $message = $webhookNotification->kind; // "subscription_went_past_due"

        nlog($message);

        if ($message == 'transaction_settlement_declined') {
            $payment = Payment::withTrashed()->where('transaction_reference', $webhookNotification->transaction->id)->first();

            if ($payment && $payment->status_id == Payment::STATUS_COMPLETED) {
                $payment->service()->deletePayment();
                $payment->status_id = Payment::STATUS_FAILED;
                $payment->save();
            }

        }

        return response()->json([], 200);
    }

    public function auth(): string
    {

        try {
            $ct = $this->init()->gateway->clientToken()->generate();

            return 'ok';
        } catch (\Exception $e) {

        }

        return 'error';
    }

    private function find(string $customer_id = '')
    {

        try {
            return $this->init()->gateway->customer()->find($customer_id);
        } catch (\Exception $e) {
            return false;
        }

    }

    private function findTokens(string $gateway_customer_reference)
    {
        return ClientGatewayToken::where('company_id', $this->company_gateway->company_id)
                                 ->where('gateway_customer_reference', $gateway_customer_reference)
                                 ->exists();
    }

    private function getToken(string $token, string $gateway_customer_reference)
    {

        return ClientGatewayToken::where('company_id', $this->company_gateway->company_id)
                                 ->where('gateway_customer_reference', $gateway_customer_reference)
                                 ->where('token', $token)
                                 ->first();

    }

    private function findClient(string $email)
    {
        return ClientContact::where('company_id', $this->company_gateway->company_id)
                            ->where('email', $email)
                            ->first()->client ?? false;
    }

    private function addClientCards(Client $client, array $cards)
    {

        $this->client = $client;

        foreach ($cards as $card) {

            if ($this->getToken($card->token, $card->customerId) || Carbon::createFromDate($card->expirationYear, $card->expirationMonth, '1')->lt(now())) { //@phpstan-ignore-line
                continue;
            }

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $card->expirationMonth;
            $payment_meta->exp_year = (string) $card->expirationYear;
            $payment_meta->brand = (string) $card->cardType;
            $payment_meta->last4 = (string) $card->last4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $card->token,
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ];

            $this->storeGatewayToken($data, ['gateway_customer_reference' => $card->customerId]);

            nlog("adding card to customer payment profile");

        }

    }

    public function createNinjaClient(mixed $customer): Client
    {

        $client = ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);

        $b_business_address = count($customer->addresses) >= 1 ? $customer->addresses[0] : false;
        $b_shipping_address = count($customer->addresses) > 1 ? $customer->addresses[1] : false;
        $import_client_data = [];

        if ($b_business_address) {

            $braintree_address
            = [
                'address1' => $b_business_address->extendedAddress ?? '',
                'address2' => $b_business_address->streetAddress ?? '',
                'city' => $b_business_address->locality ?? '',
                'postal_code' => $b_business_address->postalCode ?? '',
                'state' => $b_business_address->region ?? '',
                'country_id' => $b_business_address->countryCodeNumeric ?? '840',
            ];

            $import_client_data = array_merge($import_client_data, $braintree_address);
        }

        if ($b_shipping_address) {

            $braintree_shipping_address
            = [
                'shipping_address1' => $b_shipping_address->extendedAddress ?? '',
                'shipping_address2' => $b_shipping_address->streetAddress ?? '',
                'shipping_city' => $b_shipping_address->locality ?? '',
                'shipping_postal_code' => $b_shipping_address->postalCode ?? '',
                'shipping_state' => $b_shipping_address->region ?? '',
                'shipping_country_id' => $b_shipping_address->countryCodeNumeric ?? '840',
            ];

            $import_client_data = array_merge($import_client_data, $braintree_shipping_address);

        }

        $client->fill($import_client_data);

        $client->phone = $customer->phone ?? '';
        $client->name = $customer->company ?? $customer->firstName;

        $settings = $client->settings;
        $settings->currency_id = (string) $this->company_gateway->company->settings->currency_id;
        $client->settings = $settings;
        $client->save();

        $contact = ClientContactFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);
        $contact->first_name = $customer->firstName ?? '';
        $contact->last_name = $customer->lastName ?? '';
        $contact->email = $customer->email ?? '';
        $contact->phone = $customer->phone ?? '';
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

    public function importCustomers()
    {
        $customers = $this->init()->gateway->customer()->all();

        foreach ($customers as $c) {

            $customer = $this->find($c->id);

            // nlog(count($customer->creditCards). " Exists for {$c->id}");

            if (!$customer) {
                continue;
            }

            $client = $this->findClient($customer->email);

            if (!$this->findTokens($c->id) && !$client) {
                //customer is not referenced in the system - create client
                $client = $this->createNinjaClient($customer);
                // nlog("Creating new Client");
            }

            $this->addClientCards($client, $customer->creditCards);

            // nlog("Adding Braintree Client: {$c->id} => {$client->id}");

        }
    }
}
