<?php namespace App\Ninja\PaymentDrivers;

use Exception;
use Cache;
use App\Models\Payment;
use App\Models\PaymentMethod;

class StripePaymentDriver extends BasePaymentDriver
{
    protected $customerReferenceParam = 'customerReference';

    public function gatewayTypes()
    {
        $types =  [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_TOKEN
        ];

        if ($this->accountGateway && $this->accountGateway->getAchEnabled()) {
            $types[] = GATEWAY_TYPE_BANK_TRANSFER;
        }

        return $types;
    }

    public function tokenize()
    {
        return $this->accountGateway->getPublishableStripeKey();
    }

    public function rules()
    {
        $rules = parent::rules();

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            $rules['authorize_ach'] = 'required';
        }

        return $rules;
    }

    protected function checkCustomerExists($customer)
    {
        $response = $this->gateway()
            ->fetchCustomer(['customerReference' => $customer->token])
            ->send();

        if ( ! $response->isSuccessful()) {
            return false;
        }

        $this->tokenResponse = $response->getData();

        // import Stripe tokens created before payment methods table was added
        if ( ! count($customer->payment_methods)) {
            if ($paymentMethod = $this->createPaymentMethod($customer)) {
                $customer->default_payment_method_id = $paymentMethod->id;
                $customer->save();
                $customer->load('payment_methods');
            }
        }

        return true;
    }

    public function isTwoStep()
    {
        return $this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER) && empty($this->input['plaidPublicToken']);
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ( ! empty($this->input['sourceToken'])) {
            $data['token'] = $this->input['sourceToken'];
            unset($data['card']);
        }

        if ( ! empty($this->input['plaidPublicToken'])) {
            $data['plaidPublicToken'] = $this->input['plaidPublicToken'];
            $data['plaidAccountId'] = $this->input['plaidAccountId'];
            unset($data['card']);
        }

        return $data;
    }

    public function createToken()
    {
        $invoice = $this->invitation->invoice;
        $client = $invoice->client;

        $data = $this->paymentDetails();
        $data['description'] = $client->getDisplayName();

        if ( ! empty($data['plaidPublicToken'])) {
            $plaidResult = $this->getPlaidToken($data['plaidPublicToken'], $data['plaidAccountId']);
            unset($data['plaidPublicToken']);
            unset($data['plaidAccountId']);
            $data['token'] = $plaidResult['stripe_bank_account_token'];
        }

        // if a customer already exists link the token to it
        if ($customer = $this->customer()) {
            $data['customerReference'] = $customer->token;
        }

        $tokenResponse = $this->gateway()
            ->createCard($data)
            ->send();

        if ($tokenResponse->isSuccessful()) {
            $this->tokenResponse = $tokenResponse->getData();

            return parent::createToken();
        } else {
            throw new Exception($tokenResponse->getMessage());
        }
    }

    public function creatingCustomer($customer)
    {
        $customer->token = $this->tokenResponse['id'];

        return $customer;
    }

    protected function creatingPaymentMethod($paymentMethod)
    {
        $data = $this->tokenResponse;

        if (!empty($data['object']) && ($data['object'] == 'card' || $data['object'] == 'bank_account')) {
            $source = $data;
        } elseif (!empty($data['object']) && $data['object'] == 'customer') {
            $sources = !empty($data['sources']) ? $data['sources'] : $data['cards'];
            $source = reset($sources['data']);
        } else {
            $source = !empty($data['source']) ? $data['source'] : $data['card'];
        }

        if ( ! $source) {
            return false;
        }

        $paymentMethod->source_reference = $source['id'];
        $paymentMethod->last4 = $source['last4'];

        if ($this->isGatewayType(GATEWAY_TYPE_CREDIT_CARD)) {

            $paymentMethod->expiration = $source['exp_year'] . '-' . $source['exp_month'] . '-01';
            $paymentMethod->payment_type_id = $this->parseCardType($source['brand']);

        } elseif ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {

            $paymentMethod->routing_number = $source['routing_number'];
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
            $paymentMethod->status = $source['status'];
            $currency = Cache::get('currencies')->where('code', strtoupper($source['currency']))->first();

            if ($currency) {
                $paymentMethod->currency_id = $currency->id;
                $paymentMethod->setRelation('currency', $currency);
            }

        }

        return $paymentMethod;
    }

    protected function creatingPayment($payment)
    {
        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            $payment->payment_status_id = $this->purchaseResponse['status'] == 'succeeded' ? PAYMENT_STATUS_COMPLETED : PAYMENT_STATUS_PENDING;
        }

        return $payment;
    }

    public function removePaymentMethod($paymentMethod)
    {
        if ( ! $paymentMethod->relationLoaded('account_gateway_token')) {
            $paymentMethod->load('account_gateway_token');
        }

        $response = $this->gateway()->deleteCard([
            'customerReference' => $paymentMethod->account_gateway_token->token,
            'cardReference' => $paymentMethod->source_reference
        ])->send();

        if ($response->isSuccessful()) {
            return parent::removePaymentMethod($paymentMethod);
        } else {
            throw new Exception($response->getMessage());
        }
    }

    private function getPlaidToken($publicToken, $accountId)
    {
        $clientId = $this->accountGateway->getPlaidClientId();
        $secret = $this->accountGateway->getPlaidSecret();

        if (!$clientId) {
            throw new Exception('plaid client id not set'); // TODO use text strings
        }

        if (!$secret) {
            throw new Exception('plaid secret not set');
        }

        try {
            $subdomain = $this->accountGateway->getPlaidEnvironment() == 'production' ? 'api' : 'tartan';
            $response = (new \GuzzleHttp\Client(['base_uri'=>"https://{$subdomain}.plaid.com"]))->request(
                'POST',
                'exchange_token',
                [
                    'allow_redirects' => false,
                    'headers'  => ['content-type' => 'application/x-www-form-urlencoded'],
                    'body' => http_build_query(array(
                        'client_id' => $clientId,
                        'secret' => $secret,
                        'public_token' => $publicToken,
                        'account_id' => $accountId,
                    ))
                ]
            );
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);

            if ($body && !empty($body['message'])) {
                throw new Exception($body['message']);
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    public function verifyBankAccount($client, $publicId, $amount1, $amount2)
    {
        $customer = $this->customer($client->id);
        $paymentMethod = PaymentMethod::clientId($client->id)
            ->wherePublicId($publicId)
            ->firstOrFail();

        // Omnipay doesn't support verifying payment methods
        // Also, it doesn't want to urlencode without putting numbers inside the brackets
        $result = $this->makeStripeCall(
            'POST',
            'customers/' . $customer->token . '/sources/' . $paymentMethod->source_reference . '/verify',
            'amounts[]=' . intval($amount1) . '&amounts[]=' . intval($amount2)
        );

        if (is_string($result)) {
            return $result;
        }

        $paymentMethod->status = PAYMENT_METHOD_STATUS_VERIFIED;
        $paymentMethod->save();

        if ( ! $customer->default_payment_method_id) {
            $customer->default_payment_method_id = $paymentMethod->id;
            $customer->save();
        }

        return true;
    }

    public function makeStripeCall($method, $url, $body = null)
    {
        $apiKey = $this->accountGateway->getConfig()->apiKey;

        if (!$apiKey) {
            return 'No API key set';
        }

        try{
            $options = [
                'headers'  => ['content-type' => 'application/x-www-form-urlencoded'],
                'auth' => [$apiKey, ''],
            ];

            if ($body) {
                $options['body'] = $body;
            }

            $response = (new \GuzzleHttp\Client(['base_uri'=>'https://api.stripe.com/v1/']))->request(
                $method,
                $url,
                $options
            );
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $response = $e->getResponse();

            $body = json_decode($response->getBody(), true);
            if ($body && $body['error'] && $body['error']['type'] == 'invalid_request_error') {
                return $body['error']['message'];
            }

            return $e->getMessage();
        }
    }

    protected function handleWebHook($input)
    {
        $eventId = array_get($input, 'id');
        $eventType= array_get($input, 'type');

        $accountGateway = $this->accountGateway;
        $accountId = $accountGateway->account_id;

        if (!$eventId) {
            throw new Exception('Missing event id');
        }

        if (!$eventType) {
            throw new Exception('Missing event type');
        }

        $supportedEvents = array(
            'charge.failed',
            'charge.succeeded',
            'customer.source.updated',
            'customer.source.deleted',
        );

        if (!in_array($eventType, $supportedEvents)) {
            return array('message' => 'Ignoring event');
        }

        // Fetch the event directly from Stripe for security
        $eventDetails = $this->makeStripeCall('GET', 'events/'.$eventId);

        if (is_string($eventDetails) || !$eventDetails) {
            throw new Exception('Could not get event details');
        }

        if ($eventType != $eventDetails['type']) {
            throw new Exception('Event type mismatch');
        }

        if (!$eventDetails['pending_webhooks']) {
            throw new Exception('This is not a pending event');
        }

        if ($eventType == 'charge.failed' || $eventType == 'charge.succeeded') {
            $charge = $eventDetails['data']['object'];
            $transactionRef = $charge['id'];

            $payment = Payment::scope(false, $accountId)->where('transaction_reference', '=', $transactionRef)->first();

            if (!$payment) {
                throw new Exception('Unknown payment');
            }

            if ($eventType == 'charge.failed') {
                if (!$payment->isFailed()) {
                    $payment->markFailed($charge['failure_message']);

                    $userMailer = app('App\Ninja\Mailers\UserMailer');
                    $userMailer->sendNotification($payment->user, $payment->invoice, 'payment_failed', $payment);
                }
            } elseif ($eventType == 'charge.succeeded') {
                $payment->markComplete();
            } elseif ($eventType == 'charge.refunded') {
                $payment->recordRefund($charge['amount_refunded'] / 100 - $payment->refunded);
            }
        } elseif($eventType == 'customer.source.updated' || $eventType == 'customer.source.deleted') {
            $source = $eventDetails['data']['object'];
            $sourceRef = $source['id'];

            $paymentMethod = PaymentMethod::scope(false, $accountId)->where('source_reference', '=', $sourceRef)->first();

            if (!$paymentMethod) {
                throw new Exception('Unknown payment method');
            }

            if ($eventType == 'customer.source.deleted') {
                $paymentMethod->delete();
            }

            /*
            } elseif ($eventType == 'customer.source.updated') {
                $this->paymentService->convertPaymentMethodFromStripe($source, null, $paymentMethod)->save();
            }
            */
        }

        return 'Processed successfully';
    }
}
