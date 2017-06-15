<?php

namespace App\Ninja\PaymentDrivers;

use App\Models\Account;
use App\Models\AccountGatewaySettings;
use App\Models\AccountGatewayToken;
use App\Models\Country;
use App\Models\GatewayType;
use App\Models\License;
use App\Models\Payment;
use App\Models\PaymentMethod;
use CreditCard;
use DateTime;
use Exception;
use Omnipay;
use Request;
use Session;
use URL;
use Utils;

class BasePaymentDriver
{
    public $invitation;
    public $accountGateway;

    protected $gatewayType;
    protected $gateway;
    protected $customer;
    protected $sourceId;
    protected $input;

    protected $customerResponse;
    protected $tokenResponse;
    protected $purchaseResponse;

    protected $sourceReferenceParam = 'token';
    protected $customerReferenceParam;
    protected $transactionReferenceParam;

    public $canRefundPayments = false;

    public function __construct($accountGateway = false, $invitation = false, $gatewayType = false)
    {
        $this->accountGateway = $accountGateway;
        $this->invitation = $invitation;
        $this->gatewayType = $gatewayType ?: $this->gatewayTypes()[0];
    }

    public function isGateway($gatewayId)
    {
        return $this->accountGateway->gateway_id == $gatewayId;
    }

    public function isValid()
    {
        return true;
    }

    // optionally pass a paymentMethod to determine the type from the token
    protected function isGatewayType($gatewayType, $paymentMethod = false)
    {
        if ($paymentMethod) {
            return $paymentMethod->gatewayType() == $gatewayType;
        } else {
            return $this->gatewayType === $gatewayType;
        }
    }

    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CREDIT_CARD,
        ];
    }

    public function handles($type)
    {
        return in_array($type, $this->gatewayTypes());
    }

    // when set to true we won't pass the card details with the form
    public function tokenize()
    {
        return false;
    }

    // set payment method as pending until confirmed
    public function isTwoStep()
    {
        return false;
    }

    public function providerName()
    {
        return strtolower($this->accountGateway->gateway->provider);
    }

    protected function invoice()
    {
        return $this->invitation->invoice;
    }

    protected function contact()
    {
        return $this->invitation->contact;
    }

    protected function client()
    {
        return $this->invoice()->client;
    }

    protected function account()
    {
        return $this->client()->account;
    }

    public function startPurchase($input = false, $sourceId = false)
    {
        $this->input = $input;
        $this->sourceId = $sourceId;

        Session::put('invitation_key', $this->invitation->invitation_key);
        Session::put($this->invitation->id . 'gateway_type', $this->gatewayType);
        Session::put($this->invitation->id . 'payment_ref', $this->invoice()->id . '_' . uniqid());

        $gateway = $this->accountGateway->gateway;

        if (! $this->meetsGatewayTypeLimits($this->gatewayType)) {
            // The customer must have hacked the URL
            Session::flash('error', trans('texts.limits_not_met'));

            return redirect()->to('view/' . $this->invitation->invitation_key);
        }

        if (! $this->isGatewayType(GATEWAY_TYPE_TOKEN)) {
            // apply gateway fees
            $invoicRepo = app('App\Ninja\Repositories\InvoiceRepository');
            $invoicRepo->setGatewayFee($this->invoice(), $this->gatewayType);
        }

        if ($this->isGatewayType(GATEWAY_TYPE_TOKEN) || $gateway->is_offsite) {
            if (Session::has('error')) {
                Session::reflash();
            } else {
                $this->completeOnsitePurchase();
                if ($redirectUrl = session('redirect_url:' . $this->invitation->invitation_key)) {
                    $separator = strpos($redirectUrl, '?') === false ? '?' : '&';

                    return redirect()->to($redirectUrl . $separator . 'invoice_id=' . $this->invoice()->public_id);
                } else {
                    Session::flash('message', trans('texts.applied_payment'));
                }
            }

            return redirect()->to('view/' . $this->invitation->invitation_key);
        }

        $data = [
            'details' => ! empty($input['details']) ? json_decode($input['details']) : false,
            'accountGateway' => $this->accountGateway,
            'acceptedCreditCardTypes' => $this->accountGateway->getCreditcardTypes(),
            'gateway' => $gateway,
            'showAddress' => $this->accountGateway->show_address,
            'showBreadcrumbs' => false,
            'url' => 'payment/' . $this->invitation->invitation_key,
            'amount' => $this->invoice()->getRequestedAmount(),
            'invoiceNumber' => $this->invoice()->invoice_number,
            'client' => $this->client(),
            'contact' => $this->invitation->contact,
            'invitation' => $this->invitation,
            'gatewayType' => $this->gatewayType,
            'currencyId' => $this->client()->getCurrencyId(),
            'currencyCode' => $this->client()->getCurrencyCode(),
            'account' => $this->account(),
            'sourceId' => $sourceId,
            'tokenize' => $this->tokenize(),
            'transactionToken' => $this->createTransactionToken(),
        ];

        return view($this->paymentView(), $data);
    }

    // check if a custom view exists for this provider
    protected function paymentView()
    {
        $gatewayTypeAlias = GatewayType::getAliasFromId($this->gatewayType);

        $file = sprintf('%s/views/payments/%s/%s.blade.php', resource_path(), $this->providerName(), $gatewayTypeAlias);

        if (file_exists($file)) {
            return sprintf('payments.%s/%s', $this->providerName(), $gatewayTypeAlias);
        } else {
            return sprintf('payments.%s', $gatewayTypeAlias);
        }
    }

    // check if a custom partial exists for this provider
    public function partialView()
    {
        $file = sprintf('%s/views/payments/%s/partial.blade.php', resource_path(), $this->providerName());

        if (file_exists($file)) {
            return sprintf('payments.%s.partial', $this->providerName());
        } else {
            return false;
        }
    }

    public function rules()
    {
        $rules = [];

        if ($this->isGatewayType(GATEWAY_TYPE_CREDIT_CARD)) {
            $rules = array_merge($rules, [
                'first_name' => 'required',
                'last_name' => 'required',
            ]);

            // TODO check this is always true
            if (! $this->tokenize()) {
                $rules = array_merge($rules, [
                    'card_number' => 'required',
                    'expiration_month' => 'required',
                    'expiration_year' => 'required',
                    'cvv' => 'required',
                ]);
            }

            if ($this->accountGateway->show_address) {
                $rules = array_merge($rules, [
                    'address1' => 'required',
                    'city' => 'required',
                    'state' => 'required',
                    'postal_code' => 'required',
                    'country_id' => 'required',
                ]);
            }
        }

        return $rules;
    }

    protected function gateway()
    {
        if ($this->gateway) {
            return $this->gateway;
        }

        $this->gateway = Omnipay::create($this->accountGateway->gateway->provider);
        $this->gateway->initialize((array) $this->accountGateway->getConfig());

        return $this->gateway;
    }

    public function completeOnsitePurchase($input = false, $paymentMethod = false)
    {
        $this->input = count($input) ? $input : false;
        $gateway = $this->gateway();

        if ($input) {
            $this->updateClient();
        }

        // load or create token
        if ($this->isGatewayType(GATEWAY_TYPE_TOKEN)) {
            if (! $paymentMethod) {
                $paymentMethod = PaymentMethod::clientId($this->client()->id)
                    ->wherePublicId($this->sourceId)
                    ->firstOrFail();
            }

            $invoicRepo = app('App\Ninja\Repositories\InvoiceRepository');
            $invoicRepo->setGatewayFee($this->invoice(), $paymentMethod->payment_type->gateway_type_id);

            if (! $this->meetsGatewayTypeLimits($paymentMethod->payment_type->gateway_type_id)) {
                // The customer must have hacked the URL
                Session::flash('error', trans('texts.limits_not_met'));

                return redirect()->to('view/' . $this->invitation->invitation_key);
            }
        } else {
            if ($this->shouldCreateToken()) {
                $paymentMethod = $this->createToken();
            }

            if (! $this->meetsGatewayTypeLimits($this->gatewayType)) {
                // The customer must have hacked the URL
                Session::flash('error', trans('texts.limits_not_met'));

                return redirect()->to('view/' . $this->invitation->invitation_key);
            }
        }

        if ($this->isTwoStep()) {
            return;
        }

        // prepare and process payment
        $data = $this->paymentDetails($paymentMethod);
        $response = $gateway->purchase($data)->send();
        $this->purchaseResponse = (array) $response->getData();

        // parse the transaction reference
        if ($this->transactionReferenceParam) {
            $ref = $this->purchaseResponse[$this->transactionReferenceParam];
        } else {
            $ref = $response->getTransactionReference();
        }

        // wrap up
        if ($response->isSuccessful() && $ref) {
            $payment = $this->createPayment($ref, $paymentMethod);

            // TODO move this to stripe driver
            if ($this->invitation->invoice->account->isNinjaAccount()) {
                Session::flash('trackEventCategory', '/account');
                Session::flash('trackEventAction', '/buy_pro_plan');
                Session::flash('trackEventAmount', $payment->amount);
            }

            return $payment;
        } elseif ($response->isRedirect()) {
            $this->invitation->transaction_reference = $ref;
            $this->invitation->save();
            //Session::put('transaction_reference', $ref);
            Session::save();
            $response->redirect();
        } else {
            throw new Exception($response->getMessage() ?: trans('texts.payment_error'));
        }
    }

    private function updateClient()
    {
        if (! $this->isGatewayType(GATEWAY_TYPE_CREDIT_CARD)) {
            return;
        }

        // update the contact info
        if (! $this->contact()->getFullName()) {
            $this->contact()->first_name = $this->input['first_name'];
            $this->contact()->last_name = $this->input['last_name'];
        }

        if (! $this->contact()->email) {
            $this->contact()->email = $this->input['email'];
        }

        if ($this->contact()->isDirty()) {
            $this->contact()->save();
        }

        if (! $this->accountGateway->show_address || ! $this->accountGateway->update_address) {
            return;
        }

        // update the address info
        $client = $this->client();
        $client->address1 = trim($this->input['address1']);
        $client->address2 = trim($this->input['address2']);
        $client->city = trim($this->input['city']);
        $client->state = trim($this->input['state']);
        $client->postal_code = trim($this->input['postal_code']);
        $client->country_id = trim($this->input['country_id']);
        $client->save();
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $invoice = $this->invoice();
        $gatewayTypeAlias = $this->gatewayType == GATEWAY_TYPE_TOKEN ? $this->gatewayType : GatewayType::getAliasFromId($this->gatewayType);
        $completeUrl = $this->invitation->getLink('complete', true) . '/' . $gatewayTypeAlias;

        $data = [
            'amount' => $invoice->getRequestedAmount(),
            'currency' => $invoice->getCurrencyCode(),
            'returnUrl' => $completeUrl,
            'cancelUrl' => $this->invitation->getLink(),
            'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            'transactionId' => $invoice->invoice_number,
            'transactionType' => 'Purchase',
            'ip' => Request::getClientIp(),
        ];

        if ($paymentMethod) {
            if ($this->customerReferenceParam) {
                $data[$this->customerReferenceParam] = $paymentMethod->account_gateway_token->token;
            }
            $data[$this->sourceReferenceParam] = $paymentMethod->source_reference;
        } elseif ($this->input) {
            $data['card'] = new CreditCard($this->paymentDetailsFromInput($this->input));
        } else {
            $data['card'] = new CreditCard($this->paymentDetailsFromClient());
        }

        return $data;
    }

    private function paymentDetailsFromInput($input)
    {
        $invoice = $this->invoice();
        $client = $this->client();

        $data = [
            'company' => $client->getDisplayName(),
            'firstName' => isset($input['first_name']) ? $input['first_name'] : null,
            'lastName' => isset($input['last_name']) ? $input['last_name'] : null,
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
            // TODO use cache instead
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
                'shippingCountry' => $country->iso_3166_2,
            ]);
        }

        return $data;
    }

    public function paymentDetailsFromClient()
    {
        $invoice = $this->invoice();
        $client = $this->client();
        $contact = $this->invitation->contact ?: $client->contacts()->first();

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

    protected function shouldCreateToken()
    {
        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            return true;
        }

        if (! $this->handles(GATEWAY_TYPE_TOKEN)) {
            return false;
        }

        if ($this->account()->token_billing_type_id == TOKEN_BILLING_ALWAYS) {
            return true;
        }

        return boolval(array_get($this->input, 'token_billing'));
    }

    /*
    protected function tokenDetails()
    {
        $details = [];

        if ($customer = $this->customer()) {
            $details['customerReference'] = $customer->token;
        }

        return $details;
    }
    */

    public function customer($clientId = false)
    {
        if ($this->customer) {
            return $this->customer;
        }

        if (! $clientId) {
            $clientId = $this->client()->id;
        }

        $this->customer = AccountGatewayToken::clientAndGateway($clientId, $this->accountGateway->id)
                            ->with('payment_methods')
                            ->first();

        if ($this->customer && $this->invitation) {
            $this->customer = $this->checkCustomerExists($this->customer) ? $this->customer : null;
        }

        return $this->customer;
    }

    protected function checkCustomerExists($customer)
    {
        return true;
    }

    public function verifyBankAccount($client, $publicId, $amount1, $amount2)
    {
        throw new Exception('verifyBankAccount not implemented');
    }

    public function removePaymentMethod($paymentMethod)
    {
        $paymentMethod->delete();
    }

    // Some gateways (ie, Checkout.com and Braintree) require generating a token before paying for the invoice
    public function createTransactionToken()
    {
        return null;
    }

    public function createToken()
    {
        $account = $this->account();

        if (! $customer = $this->customer()) {
            $customer = new AccountGatewayToken();
            $customer->account_id = $account->id;
            $customer->contact_id = $this->invitation->contact_id;
            $customer->account_gateway_id = $this->accountGateway->id;
            $customer->client_id = $this->client()->id;
            $customer = $this->creatingCustomer($customer);
            $customer->save();
        }

        // archive the old payment method
        $paymentMethod = PaymentMethod::clientId($this->client()->id)
            ->isBankAccount($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER))
            ->first();

        if ($paymentMethod) {
            $paymentMethod->delete();
        }

        $paymentMethod = $this->createPaymentMethod($customer);

        if ($paymentMethod) {
            $customer->default_payment_method_id = $paymentMethod->id;
            $customer->save();
        }

        return $paymentMethod;
    }

    protected function creatingCustomer($customer)
    {
        return $customer;
    }

    public function createPaymentMethod($customer)
    {
        $paymentMethod = PaymentMethod::createNew($this->invitation);
        $paymentMethod->contact_id = $this->contact()->id;
        $paymentMethod->ip = Request::ip();
        $paymentMethod->account_gateway_token_id = $customer->id;
        $paymentMethod->setRelation('account_gateway_token', $customer);
        $paymentMethod = $this->creatingPaymentMethod($paymentMethod);

        if ($paymentMethod) {
            // archive the old payment method
            $oldPaymentMethod = PaymentMethod::clientId($this->client()->id)
                ->wherePaymentTypeId($paymentMethod->payment_type_id)
                ->first();

            if ($oldPaymentMethod) {
                $oldPaymentMethod->delete();
            }

            $paymentMethod->save();
        }

        return $paymentMethod;
    }

    protected function creatingPaymentMethod($paymentMethod)
    {
        return $paymentMethod;
    }

    public function deleteToken()
    {
    }

    public function createPayment($ref = false, $paymentMethod = null)
    {
        $account = $this->account();
        $invitation = $this->invitation;
        $invoice = $this->invoice();
        $invoice->markSentIfUnsent();

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $this->accountGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->getRequestedAmount();
        $payment->client_id = $invoice->client_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = $account->getDateTime()->format('Y-m-d');
        $payment->ip = Request::ip();

        $payment = $this->creatingPayment($payment, $paymentMethod);

        if ($paymentMethod) {
            $payment->last4 = $paymentMethod->last4;
            $payment->expiration = $paymentMethod->expiration;
            $payment->routing_number = $paymentMethod->routing_number;
            $payment->payment_type_id = $paymentMethod->payment_type_id;
            $payment->email = $paymentMethod->email;
            $payment->bank_name = $paymentMethod->bank_name;
            $payment->payment_method_id = $paymentMethod->id;
        }

        $payment->save();

        $accountKey = $invoice->account->account_key;

        if ($accountKey == env('NINJA_LICENSE_ACCOUNT_KEY')) {
            $this->createLicense($payment);
        // TODO move this code
        // enable pro plan for hosted users
        } elseif ($invoice->account->isNinjaAccount()) {
            foreach ($invoice->invoice_items as $invoice_item) {
                // Hacky, but invoices don't have meta fields to allow us to store this easily
                if (1 == preg_match('/^Plan - (.+) \((.+)\)$/', $invoice_item->product_key, $matches)) {
                    $plan = strtolower($matches[1]);
                    $term = strtolower($matches[2]);
                    $price = $invoice_item->cost;
                    if ($plan == PLAN_ENTERPRISE) {
                        preg_match('/###[\d] [\w]* (\d*)/', $invoice_item->notes, $numUserMatches);
                        if (count($numUserMatches)) {
                            $numUsers = $numUserMatches[1];
                        } else {
                            $numUsers = 5;
                        }
                    } else {
                        $numUsers = 1;
                    }
                }
            }

            if (! empty($plan)) {
                $account = Account::with('users')->find($invoice->client->public_id);
                $company = $account->company;

                if (
                    $company->plan != $plan
                    || DateTime::createFromFormat('Y-m-d', $account->company->plan_expires) <= date_create('-7 days')
                ) {
                    // Either this is a different plan, or the subscription expired more than a week ago
                    // Reset any grandfathering
                    $company->plan_started = date_create()->format('Y-m-d');
                }

                if (
                    $company->plan == $plan
                    && $company->plan_term == $term
                    && DateTime::createFromFormat('Y-m-d', $company->plan_expires) >= date_create()
                ) {
                    // This is a renewal; mark it paid as of when this term expires
                    $company->plan_paid = $company->plan_expires;
                } else {
                    $company->plan_paid = date_create()->format('Y-m-d');
                }

                $company->payment_id = $payment->id;
                $company->plan = $plan;
                $company->plan_term = $term;
                $company->plan_price = $price;
                $company->num_users = $numUsers;
                $company->plan_expires = DateTime::createFromFormat('Y-m-d', $account->company->plan_paid)
                    ->modify($term == PLAN_TERM_MONTHLY ? '+1 month' : '+1 year')->format('Y-m-d');

                if ($company->hasActivePromo()) {
                    $company->discount_expires = date_create()->modify('1 year')->format('Y-m-d');
                    $company->promo_expires = null;
                }

                $company->save();
            }
        }

        return $payment;
    }

    protected function createLicense($payment)
    {
        // TODO parse invoice to determine license
        if ($payment->amount == 20) {
            $affiliateId = 4;
            $productId = PRODUCT_WHITE_LABEL;
        } else {
            $affiliateId = 1;
            $productId = PRODUCT_ONE_CLICK_INSTALL;
        }

        $license = new License();
        $license->first_name = $this->contact()->first_name;
        $license->last_name = $this->contact()->last_name;
        $license->email = $this->contact()->email;
        $license->transaction_reference = $payment->transaction_reference;
        $license->license_key = Utils::generateLicense();
        $license->affiliate_id = $affiliateId;
        $license->product_id = $productId;
        $license->save();

        // Add the license key to the invoice content
        $invoiceItem = $payment->invoice->invoice_items->first();
        $invoiceItem->notes .= "\n\n##{$license->license_key}";
        $invoiceItem->save();

        // Add the license key to the redirect URL
        $key = 'redirect_url:' . $payment->invitation->invitation_key;
        $redirectUrl = session($key);
        session([$key => "{$redirectUrl}?license_key={$license->license_key}&product_id={$productId}"]);
    }

    protected function creatingPayment($payment, $paymentMethod)
    {
        return $payment;
    }

    public function refundPayment($payment, $amount = 0)
    {
        if ($amount) {
            $amount = min($amount, $payment->getCompletedAmount());
        } else {
            $amount = $payment->getCompletedAmount();
        }

        if (! $amount) {
            return false;
        }

        if ($payment->payment_type_id == PAYMENT_TYPE_CREDIT) {
            return $payment->recordRefund($amount);
        }

        $details = $this->refundDetails($payment, $amount);
        $response = $this->gateway()->refund($details)->send();

        if ($response->isSuccessful()) {
            return $payment->recordRefund($amount);
        } elseif ($this->attemptVoidPayment($response, $payment, $amount)) {
            $details = ['transactionReference' => $payment->transaction_reference];
            $response = $this->gateway->void($details)->send();
            if ($response->isSuccessful()) {
                return $payment->markVoided();
            }
        }

        return false;
    }

    protected function refundDetails($payment, $amount)
    {
        return [
            'amount' => $amount,
            'transactionReference' => $payment->transaction_reference,
        ];
    }

    protected function attemptVoidPayment($response, $payment, $amount)
    {
        // Partial refund not allowed for unsettled transactions
        return $amount == $payment->amount;
    }

    protected function createLocalPayment($payment)
    {
        return $payment;
    }

    public function completeOffsitePurchase($input)
    {
        $this->input = $input;
        $ref = array_get($this->input, 'token') ?: $this->invitation->transaction_reference;

        if (method_exists($this->gateway(), 'completePurchase')) {
            $details = $this->paymentDetails();
            $response = $this->gateway()->completePurchase($details)->send();
            $ref = $response->getTransactionReference() ?: $ref;

            if ($response->isCancelled()) {
                return false;
            } elseif (! $response->isSuccessful()) {
                throw new Exception($response->getMessage());
            }
        }

        // check invoice still has balance
        if (! floatval($this->invoice()->balance)) {
            throw new Exception(trans('texts.payment_error_code', ['code' => 'NB']));
        }

        // check this isn't a duplicate transaction reference
        if (Payment::whereAccountId($this->invitation->account_id)
                ->whereTransactionReference($ref)
                ->first()) {
            throw new Exception(trans('texts.payment_error_code', ['code' => 'DT']));
        }

        return $this->createPayment($ref);
    }

    public function tokenLinks()
    {
        if (! $this->customer()) {
            return [];
        }

        $paymentMethods = $this->customer()->payment_methods;
        $links = [];

        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->payment_type_id == PAYMENT_TYPE_ACH && $paymentMethod->status != PAYMENT_METHOD_STATUS_VERIFIED) {
                continue;
            }

            if (! $this->meetsGatewayTypeLimits($paymentMethod->payment_type->gateway_type_id)) {
                continue;
            }

            $url = URL::to("/payment/{$this->invitation->invitation_key}/token/".$paymentMethod->public_id);

            if ($paymentMethod->payment_type_id == PAYMENT_TYPE_ACH) {
                if ($paymentMethod->bank_name) {
                    $label = $paymentMethod->bank_name;
                } else {
                    $label = trans('texts.use_bank_on_file');
                }
            } elseif ($paymentMethod->payment_type_id == PAYMENT_TYPE_PAYPAL) {
                $label = 'PayPal: ' . $paymentMethod->email;
            } else {
                $label = trans('texts.payment_type_on_file', ['type' => $paymentMethod->payment_type->name]);
            }

            $label .= $this->invoice()->present()->gatewayFee($paymentMethod->payment_type->gateway_type_id);

            $links[] = [
                'url' => $url,
                'label' => $label,
            ];
        }

        return $links;
    }

    public function paymentLinks()
    {
        $links = [];

        foreach ($this->gatewayTypes() as $gatewayTypeId) {
            if ($gatewayTypeId === GATEWAY_TYPE_TOKEN) {
                continue;
            }

            if (! $this->meetsGatewayTypeLimits($gatewayTypeId)) {
                continue;
            }

            $gatewayTypeAlias = GatewayType::getAliasFromId($gatewayTypeId);

            if ($gatewayTypeId == GATEWAY_TYPE_CUSTOM) {
                $url = 'javascript:showCustomModal();';
                $label = e($this->accountGateway->getConfigField('name'));
            } else {
                $url = $this->paymentUrl($gatewayTypeAlias);
                $label = trans("texts.{$gatewayTypeAlias}");
            }

            $label .= $this->invoice()->present()->gatewayFee($gatewayTypeId);

            $links[] = [
                'gatewayTypeId' => $gatewayTypeId,
                'url' => $url,
                'label' => $label,
            ];
        }

        return $links;
    }

    public function supportsGatewayType($gatewayTypeId)
    {
        return in_array($gatewayTypeId, $this->gatewayTypes());
    }

    protected function meetsGatewayTypeLimits($gatewayTypeId)
    {
        if (! $gatewayTypeId) {
            return true;
        }

        $accountGatewaySettings = AccountGatewaySettings::scope(false, $this->invitation->account_id)
            ->where('account_gateway_settings.gateway_type_id', '=', $gatewayTypeId)->first();

        if ($accountGatewaySettings) {
            $invoice = $this->invoice();

            if ($accountGatewaySettings->min_limit !== null && $invoice->balance < $accountGatewaySettings->min_limit) {
                return false;
            }

            if ($accountGatewaySettings->max_limit !== null && $invoice->balance > $accountGatewaySettings->max_limit) {
                return false;
            }
        }

        return true;
    }

    protected function paymentUrl($gatewayTypeAlias)
    {
        $account = $this->account();
        $url = URL::to("/payment/{$this->invitation->invitation_key}/{$gatewayTypeAlias}");

        return $url;
    }

    protected function parseCardType($cardName)
    {
        $cardTypes = [
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
            'switch' => PAYMENT_TYPE_SWITCH,
        ];

        $cardName = strtolower(str_replace([' ', '-', '_'], '', $cardName));

        if (empty($cardTypes[$cardName]) && 1 == preg_match('/^('.implode('|', array_keys($cardTypes)).')/', $cardName, $matches)) {
            // Some gateways return extra stuff after the card name
            $cardName = $matches[1];
        }

        if (! empty($cardTypes[$cardName])) {
            return $cardTypes[$cardName];
        } else {
            return PAYMENT_TYPE_CREDIT_CARD_OTHER;
        }
    }

    public function handleWebHook($input)
    {
        throw new Exception('Unsupported gateway');
    }
}
