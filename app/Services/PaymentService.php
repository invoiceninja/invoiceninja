<?php namespace App\Services;

use Utils;
use Auth;
use URL;
use DateTime;
use Event;
use Cache;
use Omnipay;
use Session;
use CreditCard;
use WePay;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Country;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Activity;
use App\Models\AccountGateway;
use App\Http\Controllers\PaymentController;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;
use App\Ninja\Datatables\PaymentDatatable;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;

    public function __construct(PaymentRepository $paymentRepo, AccountRepository $accountRepo, DatatableService $datatableService)
    {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->accountRepo = $accountRepo;
    }

    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    public function createGateway($accountGateway)
    {
        $gateway = Omnipay::create($accountGateway->gateway->provider);
        $gateway->initialize((array)$accountGateway->getConfig());

        if ($accountGateway->isGateway(GATEWAY_DWOLLA)) {
            if ($gateway->getSandbox() && isset($_ENV['DWOLLA_SANDBOX_KEY']) && isset($_ENV['DWOLLA_SANSBOX_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_SANDBOX_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SANSBOX_SECRET']);
            } elseif (isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SECRET']);
            }
        }

        return $gateway;
    }

    public function getPaymentDetails($invitation, $accountGateway, $input = null)
    {
        $invoice = $invitation->invoice;
        $account = $invoice->account;
        $key = $invoice->account_id . '-' . $invoice->invoice_number;
        $currencyCode = $invoice->client->currency ? $invoice->client->currency->code : ($invoice->account->currency ? $invoice->account->currency->code : 'USD');

        if ($input) {
            $data = self::convertInputForOmnipay($input);
            Session::put($key, $data);
        } elseif (Session::get($key)) {
            $data = Session::get($key);
        } else {
            $data = $this->createDataForClient($invitation);
        }

        $card = !empty($data['number']) ? new CreditCard($data) : null;
        $data = [
            'amount' => $invoice->getRequestedAmount(),
            'card' => $card,
            'currency' => $currencyCode,
            'returnUrl' => URL::to('complete'),
            'cancelUrl' => $invitation->getLink(),
            'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            'transactionId' => $invoice->invoice_number,
            'transactionType' => 'Purchase',
        ];

        if ($input !== null) {
            $data['ip'] = \Request::ip();
        }

        if ($accountGateway->isGateway(GATEWAY_PAYPAL_EXPRESS) || $accountGateway->isGateway(GATEWAY_PAYPAL_PRO)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        };

        if ($input) {
            if (!empty($input['sourceToken'])) {
                $data['token'] = $input['sourceToken'];
                unset($data['card']);
            } elseif (!empty($input['plaidPublicToken'])) {
                $data['plaidPublicToken'] = $input['plaidPublicToken'];
                $data['plaidAccountId'] = $input['plaidAccountId'];
                unset($data['card']);
            }
        }

        if ($accountGateway->isGateway(GATEWAY_WEPAY) && $transactionId = Session::get($invitation->id.'payment_ref')) {
            $data['transaction_id'] = $transactionId;
        }

        return $data;
    }

    public function convertInputForOmnipay($input)
    {
        $data = [
            'firstName' => isset($input['first_name']) ? $input['first_name'] : null,
            'lastName' =>isset($input['last_name']) ? $input['last_name'] : null,
            'email' => isset($input['email']) ? $input['email'] : null,
            'number' => isset($input['card_number']) ? $input['card_number'] : null,
            'expiryMonth' => isset($input['expiration_month']) ? $input['expiration_month'] : null,
            'expiryYear' => isset($input['expiration_year']) ? $input['expiration_year'] : null,
        ];

        // allow space until there's a setting to disable
        if (isset($input['cvv']) && $input['cvv'] != ' ') {
            $data['cvv'] = $input['cvv'];
        }

        if (isset($input['address1'])) {
            $country = Country::find($input['country_id']);

            $data = array_merge($data, [
                'billingAddress1' => $input['address1'],
                'billingAddress2' => $input['address2'],
                'billingCity' => $input['city'],
                'billingState' => $input['state'],
                'billingPostcode' => $input['postal_code'],
                'billingCountry' => $country->iso_3166_2,
                'shippingAddress1' => $input['address1'],
                'shippingAddress2' => $input['address2'],
                'shippingCity' => $input['city'],
                'shippingState' => $input['state'],
                'shippingPostcode' => $input['postal_code'],
                'shippingCountry' => $country->iso_3166_2
            ]);
        }

        return $data;
    }

    public function createDataForClient($invitation)
    {
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $contact = $invitation->contact ?: $client->contacts()->first();

        return [
            'email' => $contact->email,
            'company' => $client->getDisplayName(),
            'firstName' => $contact->first_name,
            'lastName' => $contact->last_name,
            'billingAddress1' => $client->address1,
            'billingAddress2' => $client->address2,
            'billingCity' => $client->city,
            'billingPostcode' => $client->postal_code,
            'billingState' => $client->state,
            'billingCountry' => $client->country ? $client->country->iso_3166_2 : '',
            'billingPhone' => $contact->phone,
            'shippingAddress1' => $client->address1,
            'shippingAddress2' => $client->address2,
            'shippingCity' => $client->city,
            'shippingPostcode' => $client->postal_code,
            'shippingState' => $client->state,
            'shippingCountry' => $client->country ? $client->country->iso_3166_2 : '',
            'shippingPhone' => $contact->phone,
        ];
    }

    public function getClientPaymentMethods($client)
    {
        $token = $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);
        if (!$token) {
            return null;
        }

        if (!$accountGatewayToken->uses_local_payment_methods && $accountGateway->gateway_id == GATEWAY_STRIPE) {
            // Migrate Stripe data
            $gateway = $this->createGateway($accountGateway);
            $response = $gateway->fetchCustomer(array('customerReference' => $token))->send();
            if (!$response->isSuccessful()) {
                return null;
            }

            $data = $response->getData();
            $sources_list = isset($data['sources']) ? $data['sources'] : $data['cards'];
            $sources = isset($sources_list['data'])?$sources_list['data']:$sources_list;

            // Load
            $accountGatewayToken->payment_methods();
            foreach ($sources as $source) {
                $paymentMethod = $this->convertPaymentMethodFromStripe($source, $accountGatewayToken);
                if ($paymentMethod) {
                    $paymentMethod->save();
                }

                if ($data['default_source'] == $source['id']) {
                    $accountGatewayToken->default_payment_method_id = $paymentMethod->id;
                }
            }

            $accountGatewayToken->uses_local_payment_methods = true;
            $accountGatewayToken->save();
        }

        return $accountGatewayToken->payment_methods;
    }

    public function verifyClientPaymentMethod($client, $publicId, $amount1, $amount2)
    {
        $token = $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);
        if ($accountGateway->gateway_id != GATEWAY_STRIPE) {
            return 'Unsupported gateway';
        }

        $paymentMethod = PaymentMethod::scope($publicId, $client->account_id, $accountGatewayToken->id)->firstOrFail();

        // Omnipay doesn't support verifying payment methods
        // Also, it doesn't want to urlencode without putting numbers inside the brackets
        $result = $this->makeStripeCall(
            $accountGateway,
            'POST',
            'customers/' . $token . '/sources/' . $paymentMethod->source_reference . '/verify',
            'amounts[]=' . intval($amount1) . '&amounts[]=' . intval($amount2)
        );

        if (is_string($result)) {
            return $result;
        }

        $paymentMethod->status = PAYMENT_METHOD_STATUS_VERIFIED;
        $paymentMethod->save();

        if (!$paymentMethod->account_gateway_token->default_payment_method_id) {
            $paymentMethod->account_gateway_token->default_payment_method_id = $paymentMethod->id;
            $paymentMethod->account_gateway_token->save();
        }

        return true;
    }

    public function removeClientPaymentMethod($client, $publicId)
    {
        $token = $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);
        if (!$token) {
            return null;
        }

        $paymentMethod = PaymentMethod::scope($publicId, $client->account_id, $accountGatewayToken->id)->firstOrFail();

        $gateway = $this->createGateway($accountGateway);

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $response = $gateway->deleteCard(array('customerReference' => $token, 'cardReference' => $paymentMethod->source_reference))->send();
            if (!$response->isSuccessful()) {
                return $response->getMessage();
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $response = $gateway->deletePaymentMethod(array('token' => $paymentMethod->source_reference))->send();

            if (!$response->isSuccessful()) {
                return $response->getMessage();
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            try {
                $wepay = Utils::setupWePay($accountGateway);
                $wepay->request('/credit_card/delete', [
                    'client_id' => WEPAY_CLIENT_ID,
                    'client_secret' => WEPAY_CLIENT_SECRET,
                    'credit_card_id' => intval($paymentMethod->source_reference),
                ]);
            } catch (\WePayException $ex){
                return $ex->getMessage();
            }
        }

        $paymentMethod->delete();

        return true;
    }

    public function setClientDefaultPaymentMethod($client, $publicId)
    {
        $token = $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);
        if (!$token) {
            return null;
        }

        $paymentMethod = PaymentMethod::scope($publicId, $client->account_id, $accountGatewayToken->id)->firstOrFail();
        $paymentMethod->account_gateway_token->default_payment_method_id = $paymentMethod->id;
        $paymentMethod->account_gateway_token->save();

        return true;
    }

    public function createToken($paymentType, $gateway, $details, $accountGateway, $client, $contactId, &$customerReference = null, &$paymentMethod = null)
    {
        $customerReference = $client->getGatewayToken($accountGateway, $accountGatewayToken/* return paramenter */);

        if ($customerReference && $customerReference != CUSTOMER_REFERENCE_LOCAL) {
            $details['customerReference'] = $customerReference;

            if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
                $customerResponse = $gateway->fetchCustomer(array('customerReference' => $customerReference))->send();

                if (!$customerResponse->isSuccessful()) {
                    $customerReference = null; // The customer might not exist anymore
                }
            } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
                $customer = $gateway->findCustomer($customerReference)->send()->getData();

                if (!($customer instanceof \Braintree\Customer)) {
                    $customerReference = null; // The customer might not exist anymore
                }
            }
        }

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            if (!empty($details['plaidPublicToken'])) {
                $plaidResult = $this->getPlaidToken($accountGateway, $details['plaidPublicToken'], $details['plaidAccountId']);

                if (is_string($plaidResult)) {
                    $this->lastError = $plaidResult;
                    return;
                } elseif (!$plaidResult) {
                    $this->lastError = 'No token received from Plaid';
                    return;
                }

                unset($details['plaidPublicToken']);
                unset($details['plaidAccountId']);
                $details['token'] = $plaidResult['stripe_bank_account_token'];
            }

            $tokenResponse = $gateway->createCard($details)->send();

            if ($tokenResponse->isSuccessful()) {
                $sourceReference = $tokenResponse->getCardReference();
                if (!$customerReference) {
                    $customerReference = $tokenResponse->getCustomerReference();
                }

                if (!$sourceReference) {
                    $responseData = $tokenResponse->getData();
                    if (!empty($responseData['object']) && ($responseData['object'] == 'bank_account' || $responseData['object'] == 'card')) {
                        $sourceReference = $responseData['id'];
                    }
                }

                if ($customerReference == $sourceReference) {
                    // This customer was just created; find the card
                    $data = $tokenResponse->getData();
                    if (!empty($data['default_source'])) {
                        $sourceReference = $data['default_source'];
                    }
                }
            } else {
                $data = $tokenResponse->getData();
                if ($data && $data['error'] && $data['error']['type'] == 'invalid_request_error') {
                    $this->lastError = $data['error']['message'];
                    return;
                }
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            if (!$customerReference) {
                $tokenResponse = $gateway->createCustomer(array('customerData' => array()))->send();
                if ($tokenResponse->isSuccessful()) {
                    $customerReference = $tokenResponse->getCustomerData()->id;
                } else {
                    $this->lastError = $tokenResponse->getData()->message;
                    return;
                }
            }

            if ($customerReference) {
                $details['customerId'] = $customerReference;

                $tokenResponse = $gateway->createPaymentMethod($details)->send();
                if ($tokenResponse->isSuccessful()) {
                    $sourceReference = $tokenResponse->getData()->paymentMethod->token;
                } else {
                    $this->lastError = $tokenResponse->getData()->message;
                    return;
                }
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $wepay = Utils::setupWePay($accountGateway);
            try {
                if ($paymentType == PAYMENT_TYPE_WEPAY_ACH) {
                    // Persist bank details
                    $tokenResponse = $wepay->request('/payment_bank/persist', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'payment_bank_id' => intval($details['token']),
                    ));
                } else {
                    // Authorize credit card
                    $wepay->request('credit_card/authorize', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                    ));

                    // Update the callback uri and get the card details
                    $wepay->request('credit_card/modify', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                        'auto_update' => WEPAY_AUTO_UPDATE,
                        'callback_uri' => $accountGateway->getWebhookUrl(),
                    ));
                    $tokenResponse = $wepay->request('credit_card', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                    ));
                }

                $customerReference = CUSTOMER_REFERENCE_LOCAL;
                $sourceReference = $details['token'];
            } catch (\WePayException $ex) {
                $this->lastError = $ex->getMessage();
                return;
            }
        } else {
            return null;
        }

        if ($customerReference) {
            $accountGatewayToken = AccountGatewayToken::where('client_id', '=', $client->id)
                ->where('account_gateway_id', '=', $accountGateway->id)->first();

            if (!$accountGatewayToken) {
                $accountGatewayToken = new AccountGatewayToken();
                $accountGatewayToken->account_id = $client->account->id;
                $accountGatewayToken->contact_id = $contactId;
                $accountGatewayToken->account_gateway_id = $accountGateway->id;
                $accountGatewayToken->client_id = $client->id;
            }

            $accountGatewayToken->token = $customerReference;
            $accountGatewayToken->save();

            $paymentMethod = $this->convertPaymentMethodFromGatewayResponse($tokenResponse, $accountGateway, $accountGatewayToken, $contactId);
            $paymentMethod->ip = \Request::ip();
            $paymentMethod->save();

        } else {
            $this->lastError = $tokenResponse->getMessage();
        }

        return $sourceReference;
    }

    public function convertPaymentMethodFromStripe($source, $accountGatewayToken = null, $paymentMethod = null) {
        // Creating a new one or updating an existing one
        if (!$paymentMethod) {
            $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();
        }

        $paymentMethod->last4 = $source['last4'];
        $paymentMethod->source_reference = $source['id'];

        if ($source['object'] == 'bank_account') {
            $paymentMethod->routing_number = $source['routing_number'];
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
            $paymentMethod->status = $source['status'];
            $currency = Cache::get('currencies')->where('code', strtoupper($source['currency']))->first();
            if ($currency) {
                $paymentMethod->currency_id = $currency->id;
                $paymentMethod->setRelation('currency', $currency);
            }
        } elseif ($source['object'] == 'card') {
            $paymentMethod->expiration = $source['exp_year'] . '-' . $source['exp_month'] . '-01';
            $paymentMethod->payment_type_id = $this->parseCardType($source['brand']);
        } else {
            return null;
        }

        $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));

        return $paymentMethod;
    }

    public function convertPaymentMethodFromBraintree($source, $accountGatewayToken = null, $paymentMethod = null) {
        // Creating a new one or updating an existing one
        if (!$paymentMethod) {
            $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();
        }

        if ($source instanceof \Braintree\CreditCard) {
            $paymentMethod->payment_type_id = $this->parseCardType($source->cardType);
            $paymentMethod->last4 = $source->last4;
            $paymentMethod->expiration = $source->expirationYear . '-' . $source->expirationMonth . '-01';
        } elseif ($source instanceof \Braintree\PayPalAccount) {
            $paymentMethod->email = $source->email;
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ID_PAYPAL;
        } else {
            return null;
        }

        $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));

        $paymentMethod->source_reference = $source->token;

        return $paymentMethod;
    }

    public function convertPaymentMethodFromWePay($source, $accountGatewayToken = null, $paymentMethod = null) {
        // Creating a new one or updating an existing one
        if (!$paymentMethod) {
            $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();
        }

        if ($source->payment_bank_id) {
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
            $paymentMethod->last4 = $source->account_last_four;
            $paymentMethod->bank_name = $source->bank_name;
            $paymentMethod->source_reference = $source->payment_bank_id;

            switch($source->state) {
                case 'new':
                case 'pending':
                    $paymentMethod->status = 'new';
                    break;
                case 'authorized':
                    $paymentMethod->status = 'verified';
                    break;
            }
        } else {
            $paymentMethod->last4 = $source->last_four;
            $paymentMethod->payment_type_id = $this->parseCardType($source->credit_card_name);
            $paymentMethod->expiration = $source->expiration_year . '-' . $source->expiration_month . '-01';
            $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));

            $paymentMethod->source_reference = $source->credit_card_id;
        }

        return $paymentMethod;
    }

    public function convertPaymentMethodFromGatewayResponse($gatewayResponse, $accountGateway, $accountGatewayToken = null, $contactId = null, $existingPaymentMethod = null) {
        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $data = $gatewayResponse->getData();
            if (!empty($data['object']) && ($data['object'] == 'card' || $data['object'] == 'bank_account')) {
                $source = $data;
            } elseif (!empty($data['object']) && $data['object'] == 'customer') {
                $sources = !empty($data['sources']) ? $data['sources'] : $data['cards'];
                $source = reset($sources['data']);
            } else {
                $source = !empty($data['source']) ? $data['source'] : $data['card'];
            }

            if ($source) {
                $paymentMethod = $this->convertPaymentMethodFromStripe($source, $accountGatewayToken, $existingPaymentMethod);
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $data = $gatewayResponse->getData();

            if (!empty($data->transaction)) {
                $transaction = $data->transaction;

                if ($existingPaymentMethod) {
                    $paymentMethod = $existingPaymentMethod;
                } else {
                    $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();
                }

                if ($transaction->paymentInstrumentType == 'credit_card') {
                    $card = $transaction->creditCardDetails;
                    $paymentMethod->last4 = $card->last4;
                    $paymentMethod->expiration = $card->expirationYear . '-' . $card->expirationMonth . '-01';
                    $paymentMethod->payment_type_id = $this->parseCardType($card->cardType);
                } elseif ($transaction->paymentInstrumentType == 'paypal_account') {
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_ID_PAYPAL;
                    $paymentMethod->email = $transaction->paypalDetails->payerEmail;
                }
                $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));
            } elseif (!empty($data->paymentMethod)) {
                $paymentMethod = $this->convertPaymentMethodFromBraintree($data->paymentMethod, $accountGatewayToken, $existingPaymentMethod);
            }

        } elseif ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            if ($gatewayResponse instanceof \Omnipay\WePay\Message\CustomCheckoutResponse) {
                $wepay = \Utils::setupWePay($accountGateway);
                $paymentMethodType = $gatewayResponse->getData()['payment_method']['type'];

                $gatewayResponse = $wepay->request($paymentMethodType, array(
                    'client_id' => WEPAY_CLIENT_ID,
                    'client_secret' => WEPAY_CLIENT_SECRET,
                    $paymentMethodType.'_id' => $gatewayResponse->getData()['payment_method'][$paymentMethodType]['id'],
                ));

            }
            $paymentMethod = $this->convertPaymentMethodFromWePay($gatewayResponse, $accountGatewayToken, $existingPaymentMethod);
        }

        if (!empty($paymentMethod) && $accountGatewayToken && $contactId) {
            $paymentMethod->account_gateway_token_id = $accountGatewayToken->id;
            $paymentMethod->account_id = $accountGatewayToken->account_id;
            $paymentMethod->contact_id = $contactId;
            $paymentMethod->save();

            if (!$paymentMethod->account_gateway_token->default_payment_method_id) {
                $paymentMethod->account_gateway_token->default_payment_method_id = $paymentMethod->id;
                $paymentMethod->account_gateway_token->save();
            }
        }

        return $paymentMethod;
    }

    public function getCheckoutComToken($invitation)
    {
        $token = false;
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $invoice->account;

        $accountGateway = $account->getGatewayConfig(GATEWAY_CHECKOUT_COM);

        $response = $this->purchase($accountGateway, [
            'amount' => $invoice->getRequestedAmount(),
            'currency' => $client->currency ? $client->currency->code : ($account->currency ? $account->currency->code : 'USD')
        ])->send();

        if ($response->isRedirect()) {
            $token = $response->getTransactionReference();
        }

        Session::set($invitation->id . 'payment_type', PAYMENT_TYPE_CREDIT_CARD);

        return $token;
    }

    public function getBraintreeClientToken($account)
    {
        $token = false;

        $accountGateway = $account->getGatewayConfig(GATEWAY_BRAINTREE);
        $gateway = $this->createGateway($accountGateway);

        $token = $gateway->clientToken()->send()->getToken();

        return $token;
    }

    public function createPayment($invitation, $accountGateway, $ref, $payerId = null, $paymentDetails = null, $paymentMethod = null, $purchaseResponse = null)
    {
        $invoice = $invitation->invoice;

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $accountGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->getRequestedAmount();
        $payment->client_id = $invoice->client_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = date_create()->format('Y-m-d');

        if (!empty($paymentDetails['card'])) {
            $card = $paymentDetails['card'];
            $payment->last4 = $card->getNumberLastFour();
            $payment->payment_type_id = $this->detectCardType($card->getNumber());
        }

        if (!empty($paymentDetails['ip'])) {
            $payment->ip = $paymentDetails['ip'];
        }

        $savePaymentMethod = !empty($paymentMethod);

        // This will convert various gateway's formats to a known format
        $paymentMethod = $this->convertPaymentMethodFromGatewayResponse($purchaseResponse, $accountGateway, null, null, $paymentMethod);

        // If this is a stored payment method, we'll update it with the latest info
        if ($savePaymentMethod) {
            $paymentMethod->save();
        }

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $data = $purchaseResponse->getData();
            $payment->payment_status_id = $data['status'] == 'succeeded' ? PAYMENT_STATUS_COMPLETED : PAYMENT_STATUS_PENDING;
        }

        if ($paymentMethod) {
            if ($paymentMethod->last4) {
                $payment->last4 = $paymentMethod->last4;
            }

            if ($paymentMethod->expiration) {
                $payment->expiration = $paymentMethod->expiration;
            }

            if ($paymentMethod->routing_number) {
                $payment->routing_number = $paymentMethod->routing_number;
            }

            if ($paymentMethod->payment_type_id) {
                $payment->payment_type_id = $paymentMethod->payment_type_id;
            }

            if ($paymentMethod->email) {
                $payment->email = $paymentMethod->email;
            }

            if ($paymentMethod->bank_name) {
                $payment->bank_name = $paymentMethod->bank_name;
            }

            if ($payerId) {
                $payment->payer_id = $payerId;
            }

            if ($savePaymentMethod) {
                $payment->payment_method_id = $paymentMethod->id;
            }
        }

        $payment->save();

        // enable pro plan for hosted users
        if ($invoice->account->account_key == NINJA_ACCOUNT_KEY) {
            foreach ($invoice->invoice_items as $invoice_item) {
                // Hacky, but invoices don't have meta fields to allow us to store this easily
                if (1 == preg_match('/^Plan - (.+) \((.+)\)$/', $invoice_item->product_key, $matches)) {
                    $plan = strtolower($matches[1]);
                    $term = strtolower($matches[2]);
                } elseif ($invoice_item->product_key == 'Pending Monthly') {
                    $pending_monthly = true;
                }
            }

            if (!empty($plan)) {
                $account = Account::with('users')->find($invoice->client->public_id);

                if(
                    $account->company->plan != $plan
                    || DateTime::createFromFormat('Y-m-d', $account->company->plan_expires) >= date_create('-7 days')
                ) {
                    // Either this is a different plan, or the subscription expired more than a week ago
                    // Reset any grandfathering
                    $account->company->plan_started = date_create()->format('Y-m-d');
                }

                if (
                    $account->company->plan == $plan
                    && $account->company->plan_term == $term
                    && DateTime::createFromFormat('Y-m-d', $account->company->plan_expires) >= date_create()
                ) {
                    // This is a renewal; mark it paid as of when this term expires
                    $account->company->plan_paid = $account->company->plan_expires;
                } else {
                    $account->company->plan_paid = date_create()->format('Y-m-d');
                }

                $account->company->payment_id = $payment->id;
                $account->company->plan = $plan;
                $account->company->plan_term = $term;
                $account->company->plan_expires = DateTime::createFromFormat('Y-m-d', $account->company->plan_paid)
                    ->modify($term == PLAN_TERM_MONTHLY ? '+1 month' : '+1 year')->format('Y-m-d');

                if (!empty($pending_monthly)) {
                    $account->company->pending_plan = $plan;
                    $account->company->pending_term = PLAN_TERM_MONTHLY;
                } else {
                    $account->company->pending_plan = null;
                    $account->company->pending_term = null;
                }

                $account->company->save();
            }
        }

        return $payment;
    }

    private function parseCardType($cardName) {
        $cardTypes = array(
            'visa' => PAYMENT_TYPE_VISA,
            'americanexpress' => PAYMENT_TYPE_AMERICAN_EXPRESS,
            'amex' => PAYMENT_TYPE_AMERICAN_EXPRESS,
            'mastercard' => PAYMENT_TYPE_MASTERCARD,
            'discover' => PAYMENT_TYPE_DISCOVER,
            'jcb' => PAYMENT_TYPE_JCB,
            'dinersclub' => PAYMENT_TYPE_DINERS,
            'carteblanche' => PAYMENT_TYPE_CARTE_BLANCHE,
            'chinaunionpay' => PAYMENT_TYPE_UNIONPAY,
            'unionpay' => PAYMENT_TYPE_UNIONPAY,
            'laser' => PAYMENT_TYPE_LASER,
            'maestro' => PAYMENT_TYPE_MAESTRO,
            'solo' => PAYMENT_TYPE_SOLO,
            'switch' => PAYMENT_TYPE_SWITCH
        );

        $cardName = strtolower(str_replace(array(' ', '-', '_'), '', $cardName));

        if (empty($cardTypes[$cardName]) && 1 == preg_match('/^('.implode('|', array_keys($cardTypes)).')/', $cardName, $matches)) {
            // Some gateways return extra stuff after the card name
            $cardName = $matches[1];
        }

        if (!empty($cardTypes[$cardName])) {
            return $cardTypes[$cardName];
        } else {
            return PAYMENT_TYPE_CREDIT_CARD_OTHER;
        }
    }

    private function detectCardType($number)
    {
        if (preg_match('/^3[47][0-9]{13}$/',$number)) {
            return PAYMENT_TYPE_AMERICAN_EXPRESS;
        } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',$number)) {
            return PAYMENT_TYPE_DINERS;
        } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/',$number)) {
            return PAYMENT_TYPE_DISCOVER;
        } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/',$number)) {
            return PAYMENT_TYPE_JCB;
        } elseif (preg_match('/^5[1-5][0-9]{14}$/',$number)) {
            return PAYMENT_TYPE_MASTERCARD;
        } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/',$number)) {
            return PAYMENT_TYPE_VISA;
        }
        return PAYMENT_TYPE_CREDIT_CARD_OTHER;
    }

    public function completePurchase($gateway, $accountGateway, $details, $token)
    {
        if ($accountGateway->isGateway(GATEWAY_MOLLIE)) {
            $details['transactionReference'] = $token;
            $response = $gateway->fetchTransaction($details)->send();
            return $gateway->fetchTransaction($details)->send();
        } else {

            return $gateway->completePurchase($details)->send();
        }
    }

    public function autoBillInvoice($invoice)
    {
        $client = $invoice->client;

        // Make sure we've migrated in data from Stripe
        $this->getClientPaymentMethods($client);

        $invitation = $invoice->invitations->first();
        $token = $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);

        if (!$accountGatewayToken) {
            return false;
        }

        $defaultPaymentMethod = $accountGatewayToken->default_payment_method;

        if (!$invitation || !$token || !$defaultPaymentMethod) {
            return false;
        }

        if ($defaultPaymentMethod->requiresDelayedAutoBill()) {
            $invoiceDate = \DateTime::createFromFormat('Y-m-d', $invoice->invoice_date);
            $minDueDate = clone $invoiceDate;
            $minDueDate->modify('+10 days');

            if (date_create() < $minDueDate) {
                // Can't auto bill now
                return false;
            }

            if ($invoice->partial > 0) {
                // The amount would be different than the amount in the email
                return false;
            }

            $firstUpdate = Activity::where('invoice_id', '=', $invoice->id)
                ->where('activity_type_id', '=', ACTIVITY_TYPE_UPDATE_INVOICE)
                ->first();

            if ($firstUpdate) {
                $backup = json_decode($firstUpdate->json_backup);

                if ($backup->balance != $invoice->balance || $backup->due_date != $invoice->due_date) {
                    // It's changed since we sent the email can't bill now
                    return false;
                }
            }

            if ($invoice->payments->count()) {
                // ACH requirements are strict; don't auto bill this
                return false;
            }
        }

        // setup the gateway/payment info
        $details = $this->getPaymentDetails($invitation, $accountGateway);
        $details['customerReference'] = $token;

        $details['token'] = $defaultPaymentMethod->source_reference;
        $details['paymentType'] = $defaultPaymentMethod->payment_type_id;
        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $details['transaction_id'] = 'autobill_'.$invoice->id;
        }

        // submit purchase/get response
        $response = $this->purchase($accountGateway, $details);

        if ($response->isSuccessful()) {
            $ref = $response->getTransactionReference();
            return $this->createPayment($invitation, $accountGateway, $ref, null, $details, $defaultPaymentMethod, $response);
        } else {
            return false;
        }
    }

    public function getClientDefaultPaymentMethod($client) {
        $this->getClientPaymentMethods($client);

        $client->getGatewayToken($accountGateway/* return parameter */, $accountGatewayToken/* return parameter */);

        if (!$accountGatewayToken) {
            return false;
        }

        return $accountGatewayToken->default_payment_method;
    }

    public function getClientRequiresDelayedAutoBill($client) {
        $defaultPaymentMethod = $this->getClientDefaultPaymentMethod($client);

        return $defaultPaymentMethod?$defaultPaymentMethod->requiresDelayedAutoBill():null;
    }

    public function getDatatable($clientPublicId, $search)
    {
        $datatable = new PaymentDatatable( ! $clientPublicId, $clientPublicId);
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }


    public function bulk($ids, $action, $params = array())
    {
        if ($action == 'refund') {
            if ( ! $ids ) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            $successful = 0;

            foreach ($payments as $payment) {
                if(Auth::user()->can('edit', $payment)){
                    $amount = !empty($params['amount']) ? floatval($params['amount']) : null;
                    if ($this->refund($payment, $amount)) {
                        $successful++;
                    }
                }
            }

            return $successful;
        } else {
            return parent::bulk($ids, $action);
        }
    }

    public function refund($payment, $amount = null) {
        if ($amount) {
            $amount = min($amount, $payment->amount - $payment->refunded);
        }

        $accountGateway = $payment->account_gateway;

        if (!$accountGateway) {
            $accountGateway = AccountGateway::withTrashed()->find($payment->account_gateway_id);
        }

        if (!$amount || !$accountGateway) {
            return;
        }

        if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT) {
            $gateway = $this->createGateway($accountGateway);

            $details = array(
                'transactionReference' => $payment->transaction_reference,
            );

            if ($accountGateway->gateway_id == GATEWAY_WEPAY && $amount == $payment->getCompletedAmount()) {
                // WePay issues a full refund when no amount is set. If an amount is set, it will try
                // to issue a partial refund without refunding any fees. However, the Stripe driver
                // (but not the API) requires the amount parameter to be set no matter what.
            } else {
                $details['amount'] = $amount;
            }

            if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
                $details['refund_reason'] = 'Refund issued by merchant.';
            }

            $refund = $gateway->refund($details);
            $response = $refund->send();

            if ($response->isSuccessful()) {
                $payment->recordRefund($amount);
            } else {
                $data = $response->getData();

                if ($data instanceof \Braintree\Result\Error) {
                    $error = $data->errors->deepAll()[0];
                    if ($error && $error->code == 91506) {
                        $tryVoid = true;
                    }
                } elseif ($accountGateway->gateway_id == GATEWAY_WEPAY && $response->getCode() == 4004) {
                    $tryVoid = true;
                }

                if (!empty($tryVoid)) {
                    if ($amount == $payment->amount) {
                        // This is an unsettled transaction; try to void it
                        $void = $gateway->void(array(
                            'transactionReference' => $payment->transaction_reference,
                        ));
                        $response = $void->send();

                        if ($response->isSuccessful()) {
                            $payment->markVoided();
                        }
                    } else {
                        $this->error('Unknown', 'Partial refund not allowed for unsettled transactions.', $accountGateway);
                        return false;
                    }
                }

                if (!$response->isSuccessful()) {
                    $this->error('Unknown', $response->getMessage(), $accountGateway);
                    return false;
                }
            }
        } else {
            $payment->recordRefund($amount);
        }
        return true;
    }

    private function error($type, $error, $accountGateway = false, $exception = false)
    {
        $message = '';
        if ($accountGateway && $accountGateway->gateway) {
            $message = $accountGateway->gateway->name . ': ';
        }
        $message .= $error ?: trans('texts.payment_error');

        Session::flash('error', $message);
        Utils::logError("Payment Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message), 'PHP', true);
    }

    public function makeStripeCall($accountGateway, $method, $url, $body = null) {
        $apiKey = $accountGateway->getConfig()->apiKey;

        if (!$apiKey) {
            return 'No API key set';
        }

        try{
            $options = [
                'headers'  => ['content-type' => 'application/x-www-form-urlencoded'],
                'auth' => [$accountGateway->getConfig()->apiKey,''],
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

    private function getPlaidToken($accountGateway, $publicToken, $accountId) {
        $clientId = $accountGateway->getPlaidClientId();
        $secret = $accountGateway->getPlaidSecret();

        if (!$clientId) {
            return 'No client ID set';
        }

        if (!$secret) {
            return 'No secret set';
        }

        try{
            $subdomain = $accountGateway->getPlaidEnvironment() == 'production' ? 'api' : 'tartan';
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
                return $body['message'];
            }

            return $e->getMessage();
        }
    }

    public function purchase($accountGateway, $details) {
        $gateway = $this->createGateway($accountGateway);

        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $details['applicationFee'] = $this->calculateApplicationFee($accountGateway, $details['amount']);
            $details['feePayer'] = WEPAY_FEE_PAYER;
            $details['callbackUri'] = $accountGateway->getWebhookUrl();
            if(isset($details['paymentType'])) {
                if($details['paymentType'] == PAYMENT_TYPE_ACH || $details['paymentType'] == PAYMENT_TYPE_WEPAY_ACH) {
                    $details['paymentMethodType'] = 'payment_bank';
                }

                unset($details['paymentType']);
            }
        }

        $response = $gateway->purchase($details)->send();

        return $response;
    }

    private function calculateApplicationFee($accountGateway, $amount) {
        if ($accountGateway->gateway_id = GATEWAY_WEPAY) {
            $fee = WEPAY_APP_FEE_MULTIPLIER * $amount + WEPAY_APP_FEE_FIXED;

            return floor(min($fee, $amount * 0.2));// Maximum fee is 20% of the amount.
        }

        return 0;
    }
}
