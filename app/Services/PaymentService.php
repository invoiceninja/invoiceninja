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
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Country;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\AccountGateway;
use App\Http\Controllers\PaymentController;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;

    protected static $refundableGateways = array(
        GATEWAY_STRIPE,
        GATEWAY_BRAINTREE
    );

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

        if ($accountGateway->isGateway(GATEWAY_PAYPAL_EXPRESS) || $accountGateway->isGateway(GATEWAY_PAYPAL_PRO)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        };

        if ($input && $accountGateway->isGateway(GATEWAY_STRIPE)) {
            if (!empty($input['stripeToken'])) {
                $data['token'] = $input['stripeToken'];
                unset($details['card']);
            } elseif (!empty($input['plaidPublicToken'])) {
                $data['plaidPublicToken'] = $input['plaidPublicToken'];
                $data['plaidAccountId'] = $input['plaidAccountId'];
                unset($data['card']);
            }
        }

        return $data;
    }

    public function convertInputForOmnipay($input)
    {
        $data = [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'email' => $input['email'],
            'number' => isset($input['card_number']) ? $input['card_number'] : null,
            'expiryMonth' => isset($input['expiration_month']) ? $input['expiration_month'] : null,
            'expiryYear' => isset($input['expiration_year']) ? $input['expiration_year'] : null,
        ];

        // allow space until there's a setting to disable
        if (isset($input['cvv']) && $input['cvv'] != ' ') {
            $data['cvv'] = $input['cvv'];
        }

        if (isset($input['country_id'])) {
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
            $sources = isset($data['sources']) ? $data['sources']['data'] : $data['cards']['data'];

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
        $token = $client->getGatewayToken($accountGateway);
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

        if (!is_string($result)) {
            $paymentMethod->status = PAYMENT_METHOD_STATUS_VERIFIED;
            $paymentMethod->save();

            if (!$paymentMethod->account_gateway_token->default_payment_method_id) {
                $paymentMethod->account_gateway_token->default_payment_method_id = $paymentMethod->id;
                $paymentMethod->account_gateway_token->save();
            }
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

    public function createToken($gateway, $details, $accountGateway, $client, $contactId, &$customerReference = null, &$paymentMethod = null)
    {
        $customerReference = $client->getGatewayToken($accountGateway, $accountGatewayToken/* return paramenter */);

        if ($customerReference) {
            $details['customerReference'] = $customerReference;

            if ($accountGateway->gateway->id == GATEWAY_STRIPE) {
                $customerResponse = $gateway->fetchCustomer(array('customerReference' => $customerReference))->send();

                if (!$customerResponse->isSuccessful()) {
                    $customerReference = null; // The customer might not exist anymore
                }
            } elseif ($accountGateway->gateway->id == GATEWAY_BRAINTREE) {
                $customer = $gateway->findCustomer($customerReference)->send()->getData();

                if (!($customer instanceof \Braintree\Customer)) {
                    $customerReference = null; // The customer might not exist anymore
                }
            }
        }

        if ($accountGateway->gateway->id == GATEWAY_STRIPE) {
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
        } elseif ($accountGateway->gateway->id == GATEWAY_BRAINTREE) {
            if (!$customerReference) {
                $tokenResponse = $gateway->createCustomer(array('customerData' => array()))->send();
                if ($tokenResponse->isSuccessful()) {
                    $customerReference = $tokenResponse->getCustomerData()->id;
                }
            }

            if ($customerReference) {
                $details['customerId'] = $customerReference;

                $tokenResponse = $gateway->createPaymentMethod($details)->send();
                $sourceReference = $tokenResponse->getData()->paymentMethod->token;
            }
        }

        if ($customerReference) {
            $token = AccountGatewayToken::where('client_id', '=', $client->id)
                ->where('account_gateway_id', '=', $accountGateway->id)->first();

            if (!$token) {
                $token = new AccountGatewayToken();
                $token->account_id = $client->account->id;
                $token->contact_id = $contactId;
                $token->account_gateway_id = $accountGateway->id;
                $token->client_id = $client->id;
            }

            $token->token = $customerReference;
            $token->save();

            $paymentMethod = $this->createPaymentMethodFromGatewayResponse($tokenResponse, $accountGateway, $accountGatewayToken, $contactId);

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
            $paymentMethod->expiration = $source['exp_year'] . '-' . $source['exp_month'] . '-00';
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
            $paymentMethod->expiration = $source->expirationYear . '-' . $source->expirationMonth . '-00';
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
    
    public function createPaymentMethodFromGatewayResponse($gatewayResponse, $accountGateway, $accountGatewayToken = null, $contactId = null) {
        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $data = $gatewayResponse->getData();
            if(!empty($data['object']) && ($data['object'] == 'card' || $data['object'] == 'bank_account')) {
                $source = $data;
            } else {
                $source = !empty($data['source']) ? $data['source'] : $data['card'];
            }

            if ($source) {
                $paymentMethod = $this->convertPaymentMethodFromStripe($source, $accountGatewayToken);
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();

            $transaction = $sourceResponse->getData()->transaction;
            if ($transaction->paymentInstrumentType == 'credit_card') {
                $card = $transaction->creditCardDetails;
                $paymentMethod->last4 = $card->last4;
                $paymentMethod->expiration = $card->expirationYear . '-' . $card->expirationMonth . '-00';
                $paymentMethod->payment_type_id = $this->parseCardType($card->cardType);
            } elseif ($transaction->paymentInstrumentType == 'paypal_account') {
                $paymentMethod->payment_type_id = PAYMENT_TYPE_ID_PAYPAL;
                $paymentMethod->email = $transaction->paypalDetails->payerEmail;
            }

            $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));
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
        $gateway = $this->createGateway($accountGateway);

        $response = $gateway->purchase([
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
            $payment->last4 = substr($card->number, -4);
            $year = $card->expiryYear;
            if (strlen($year) == 2) {
                $year = '20' . $year;
            }

            $payment->expiration = $year . '-' . $card->expiryMonth . '-00';
            $payment->payment_type_id = $this->detectCardType($card->number);
        }

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $data = $purchaseResponse->getData();
            $source = !empty($data['source'])?$data['source']:$data['card'];

            $payment->payment_status_id = $data['status'] == 'succeeded' ? PAYMENT_STATUS_COMPLETED : PAYMENT_STATUS_PENDING;

            if ($source) {
                $payment->last4 = $source['last4'];

                if ($source['object'] == 'bank_account') {
                    $payment->routing_number = $source['routing_number'];
                    $payment->payment_type_id = PAYMENT_TYPE_ACH;
                }
                else{
                    $payment->expiration = $source['exp_year'] . '-' . $source['exp_month'] . '-00';
                    $payment->payment_type_id = $this->parseCardType($source['brand']);
                }
            }
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $transaction = $purchaseResponse->getData()->transaction;
            if ($transaction->paymentInstrumentType == 'credit_card') {
                $card = $transaction->creditCardDetails;
                $payment->last4 = $card->last4;
                $payment->expiration = $card->expirationYear . '-' . $card->expirationMonth . '-00';
                $payment->payment_type_id = $this->parseCardType($card->cardType);
            } elseif ($transaction->paymentInstrumentType == 'paypal_account') {
                $payment->payment_type_id = PAYMENT_TYPE_ID_PAYPAL;
                $payment->email = $transaction->paypalDetails->payerEmail;
            }
        }
        
        if ($payerId) {
            $payment->payer_id = $payerId;
        }

        if ($paymentMethod) {
            $payment->payment_method_id = $paymentMethod->id;
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
            'Visa' => PAYMENT_TYPE_VISA,
            'American Express' => PAYMENT_TYPE_AMERICAN_EXPRESS,
            'MasterCard' => PAYMENT_TYPE_MASTERCARD,
            'Discover' => PAYMENT_TYPE_DISCOVER,
            'JCB' => PAYMENT_TYPE_JCB,
            'Diners Club' => PAYMENT_TYPE_DINERS,
            'Carte Blanche' => PAYMENT_TYPE_CARTE_BLANCHE,
            'China UnionPay' => PAYMENT_TYPE_UNIONPAY,
            'Laser' => PAYMENT_TYPE_LASER,
            'Maestro' => PAYMENT_TYPE_MAESTRO,
            'Solo' => PAYMENT_TYPE_SOLO,
            'Switch' => PAYMENT_TYPE_SWITCH
        );

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
        $defaultPaymentMethod = $accountGatewayToken->default_payment_method;

        if (!$invitation || !$token || !$defaultPaymentMethod) {
            return false;
        }

        // setup the gateway/payment info
        $gateway = $this->createGateway($accountGateway);
        $details = $this->getPaymentDetails($invitation, $accountGateway);

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            $details['customerReference'] = $token;
            $details['cardReference'] = $defaultPaymentMethod->sourceReference;
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $details['customerId'] = $token;
            $details['paymentMethodToken'] = $defaultPaymentMethod->sourceReference;
        }

        // submit purchase/get response
        $response = $gateway->purchase($details)->send();

        if ($response->isSuccessful()) {
            $ref = $response->getTransactionReference();
            return $this->createPayment($invitation, $accountGateway, $ref, null, $details, $defaultPaymentMethod, $response);
        } else {
            return false;
        }
    }

    public function getDatatable($clientPublicId, $search)
    {
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_PAYMENT, $query, !$clientPublicId, false, 
                ['invoice_number', 'transaction_reference', 'payment_type', 'amount', 'payment_date']);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'invoice_number',
                function ($model) {
                    if(!Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->invoice_user_id])){
                        return $model->invoice_number;
                    }
                    
                    return link_to("invoices/{$model->invoice_public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }
                    
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $hideClient
            ],
            [
                'transaction_reference',
                function ($model) {
                    return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>';
                }
            ],
            [
                'payment_type',
                function ($model) {
                    return ($model->payment_type && !$model->last4) ? $model->payment_type : ($model->account_gateway_id ? $model->gateway_name : '');
                }
            ],
            [
                'source',
                function ($model) {
                    $code = str_replace(' ', '', strtolower($model->payment_type));
                    $card_type = trans("texts.card_" . $code);
                    if ($model->payment_type_id != PAYMENT_TYPE_ACH) {
                        if($model->last4) {
                            $expiration = trans('texts.card_expiration', array('expires' => Utils::fromSqlDate($model->expiration, false)->format('m/y')));
                            return '<img height="22" src="' . URL::to('/images/credit_cards/' . $code . '.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4 . ' ' . $expiration;
                        } elseif ($model->email) {
                            return $model->email;
                        }
                    } elseif ($model->last4) {
                        $bankData = PaymentMethod::lookupBankData($model->routing_number);
                        if (is_object($bankData)) {
                            return $bankData->name.'&nbsp; &bull;&bull;&bull;' . $model->last4;
                        } elseif($model->last4) {
                            return '<img height="22" src="' . URL::to('/images/credit_cards/ach.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4;
                        }
                    }
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'payment_date',
                function ($model) {
                    return Utils::dateToString($model->payment_date);
                }
            ],
            [
                'payment_status_name',
                function ($model) use ($entityType) {
                    return self::getStatusLabel($entityType, $model);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_payment'),
                function ($model) {
                    return URL::to("payments/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PAYMENT, $model->user_id]);
                }
            ],
            [
                trans('texts.refund_payment'),
                function ($model) {
                    $max_refund = number_format($model->amount - $model->refunded, 2);
                    $formatted = Utils::formatMoney($max_refund, $model->currency_id, $model->country_id);
                    $symbol = Utils::getFromCache($model->currency_id, 'currencies')->symbol;
                    return "javascript:showRefundModal({$model->public_id}, '{$max_refund}', '{$formatted}', '{$symbol}')";
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PAYMENT, $model->user_id]) && $model->payment_status_id >= PAYMENT_STATUS_COMPLETED &&
                    $model->refunded < $model->amount &&
                    (
                        ($model->transaction_reference && in_array($model->gateway_id , static::$refundableGateways))
                        || $model->payment_type_id == PAYMENT_TYPE_CREDIT
                    );
                }
            ]
        ];
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
    
    private function getStatusLabel($entityType, $model)
    {
        $label = trans("texts.status_" . strtolower($model->payment_status_name));
        $class = 'default';
        switch ($model->payment_status_id) {
            case PAYMENT_STATUS_PENDING:
                $class = 'info';
                break;
            case PAYMENT_STATUS_COMPLETED:
                $class = 'success';
                break;
            case PAYMENT_STATUS_FAILED:
                $class = 'danger';
                break;
            case PAYMENT_STATUS_PARTIALLY_REFUNDED:
                $label = trans('texts.status_partially_refunded_amount', [
                    'amount' => Utils::formatMoney($model->refunded, $model->currency_id, $model->country_id),
                ]);
                $class = 'primary';
                break;
            case PAYMENT_STATUS_VOIDED:
            case PAYMENT_STATUS_REFUNDED:
                $class = 'default';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
    
    public function refund($payment, $amount = null) {
        if (!$amount) {
            $amount = $payment->amount;
        }
        
        $amount = min($amount, $payment->amount - $payment->refunded);

        $accountGateway = $payment->account_gateway;
        
        if (!$accountGateway) {
            $accountGateway = AccountGateway::withTrashed()->find($payment->account_gateway_id);
        }
        
        if (!$amount || !$accountGateway) {
            return;
        }
        
        if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT) {
            $gateway = $this->createGateway($accountGateway);
            $refund = $gateway->refund(array(
                'transactionReference' => $payment->transaction_reference,
                'amount' => $amount,
            ));
            $response = $refund->send();
            
            if ($response->isSuccessful()) {
                $payment->recordRefund($amount);
            } else {
                $data = $response->getData();

                if ($data instanceof \Braintree\Result\Error) {
                    $error = $data->errors->deepAll()[0];
                    if ($error && $error->code == 91506) {
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
}
