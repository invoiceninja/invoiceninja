<?php

namespace App\Ninja\PaymentDrivers;

use Exception;
use Session;
use Utils;
use Crypt;
use Request;
use App\Models\PaymentMethod;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\AccountGatewayToken;
use Dewbud\CardConnect\CardPointe;
use Dewbud\CardConnect\Requests\AuthorizationRequest;
use Dewbud\CardConnect\Responses\AuthorizationResponse;

class CardconnectPaymentDriver extends BasePaymentDriver
{
    protected $customerReferenceParam = 'customerId';
    protected $sourceReferenceParam = 'paymentMethodToken';
    public $canRefundPayments = true;

    public function __construct($accountGateway = false, $invitation = false, $gatewayType = false)
    {
        parent::__construct($accountGateway, $invitation, $gatewayType);

        // var_dump($accountGateway->config);
        $config = Crypt::decrypt($accountGateway->config);
        $config = json_decode($config);

        $server = 'https://'.$config->apiHost.':'.$config->apiPort.'/';

        $this->cardconnect = new CardPointe($config->merchantId, $config->apiUsername, $config->apiPassword, $server);
    }

    public function gatewayTypes()
    {
        $types = [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_TOKEN
        ];

        return $types;
    }


    protected function paymentDetails($paymentMethod = false)
    {
        $invoice = $this->invoice();
        $gatewayTypeAlias = $this->gatewayType == GATEWAY_TYPE_TOKEN ? $this->gatewayType : GatewayType::getAliasFromId($this->gatewayType);
        $completeUrl = $this->invitation->getLink('complete', true) . '/' . $gatewayTypeAlias;

        $data = [
            'amount' => $invoice->getRequestedAmount(),
            'currency' => $invoice->getCurrencyCode(),
            'orderid' => $invoice->invoice_number,
            'profile' => $this->customer()->token . (!empty($paymentMethod) ? '/'.$paymentMethod->source_reference : ''),
            'capture' => true
        ];

        return $data;
    }

    /**
     * @param bool $input
     * @param bool $paymentMethod
     * @param bool $offSession True if this payment is being made automatically rather than manually initiated by the user.
     *
     * @return bool|mixed
     * @throws PaymentActionRequiredException When further interaction is required from the user.
     */
    public function completeOnsitePurchase($input = false, $paymentMethod = false, $offSession = false)
    {
        $this->input = $input;

        if(!empty($this->sourceId) && empty($paymentMethod)){
            $paymentMethod = PaymentMethod::where('id', $this->sourceId)->firstOrFail();
        }

        if(empty($paymentMethod)){ // Customer has entered in credit card info manually
            if(!empty($input['cctoken'])){
                $this->cctoken = $input['cctoken'];
            }

            $paymentDetails = $this->prepareOnsitePurchase($input, $paymentMethod);

            if (!$paymentDetails) {
                // No payment method to charge against yet; probably a 2-step or capture-only transaction.
                return null;
            }
        }else{ // Using a saved billing method
            $paymentDetails = $this->paymentDetails($paymentMethod);
        }
        

        return $this->doCardConnectOnsitePurchase($paymentDetails);
    }

    protected function checkCustomerExists($customer)
    {
        //Check if the card connect customer exists
        if(empty($customer->token)){
            return false;
        }
        
        $profile_id = $customer->token;
        $account_id = null; // optional
        $profile = $this->cardconnect->profile($profile_id, $account_id);

        if(empty($profile[0]['respstat'])){
            return true;
        }
        
        return false;
    }

    protected function createCardConnectProfile($client, $updateProfile = '')
    {
        if(empty($updateProfile)){
            //Delete any previous non-working profiles 
            $oldCustomers = AccountGatewayToken::clientAndGateway($client->id, $this->accountGateway->id)
                            ->with('payment_methods')
                            ->orderBy('id', 'desc')
                            ->delete();
        }

        //Create card connect profile
        $request = [
            'defaultacct' => "Y",
            'account'     => $this->cctoken,
            'name'        => $this->invitation->contact->first_name . ' ' . $this->invitation->contact->last_name,
            'address'     => (empty($this->input['address1']) ? $client->address1 . ' ' . $client->address2 : $this->input['address1'] . ' ' . $this->input['address2']),
            'city'        => (empty($this->input['city']) ? $client->city : $this->input['city']),
            'region'      => (empty($this->input['state']) ? $client->state : $this->input['state']),
            'postal'      => (empty($this->input['postal_code']) ? $client->postal_code : $this->input['postal_code']),
            'email'       => $this->invitation->contact->email,
            'phone'       => $this->invitation->contact->phone
        ];
        if(!empty($updateProfile)){
            $request['profile'] = $updateProfile;
        }
        $profile = $this->cardconnect->createProfile($request);
        if(isset($profile) && (empty($profile['respstat']) || $profile['respstat'] == 'A')){
            if(empty($updateProfile)){
                //CardConnect profile created, Create customer in invoice ninja
                $account = $this->account();

                $customer = new AccountGatewayToken();
                $customer->account_id = $account->id;
                $customer->contact_id = $this->invitation->contact_id;
                $customer->account_gateway_id = $this->accountGateway->id;
                $customer->client_id = $this->client()->id;
                $customer->token = $profile['profileid'];
                $customer->save();
            }else{
                $customer = $this->customer();
            }

            //Create new payment method
            $paymentMethod = PaymentMethod::createNew($this->invitation);
            $paymentMethod->contact_id = $this->contact()->id;
            $paymentMethod->ip = Request::ip();
            $paymentMethod->account_gateway_token_id = $customer->id;
            $paymentMethod->source_reference = $profile['acctid'];
            switch ($profile['accttype']) {
                case 'VISA':
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_VISA;
                    break;
                case 'MC':
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_MASTERCARD;
                    break;
                case 'AMEX':
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_AMERICAN_EXPRESS;
                    break;
                case 'DISC':
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_DISCOVER;
                    break;
                default:
                    $paymentMethod->payment_type_id = PAYMENT_TYPE_CREDIT_CARD_OTHER;
                    break;
            }
            $paymentMethod->setRelation('account_gateway_token', $customer);

            if ($paymentMethod) {
                // archive the old payment method
                $oldPaymentMethod = PaymentMethod::clientId($this->client()->id)
                    ->wherePaymentTypeId($paymentMethod->payment_type_id)
                    ->first();

                if ($oldPaymentMethod) {
                    $oldPaymentMethod->delete();
                }

                $paymentMethod->save();

                $customer->default_payment_method_id = $paymentMethod->id;
                $customer->save();
            }
            return true;
        }

        throw new Exception(trans('texts.payment_error'));
        return false;
    }

    protected function prepareOnsitePurchase($input = false, $paymentMethod = false)
    {
        $this->input = $input && count($input) ? $input : false;
        // $this->client() # has client info
        // $this->customer() # has card connect profile
        $customer = $this->customer();
        if(empty($customer)){
            $this->createCardConnectProfile($this->client());
        }else{
            // Update new payment method
            $this->createCardConnectProfile($this->client(), $this->customer()->token);
        }

        // load up payment token
        if ( ! $paymentMethod) {
            $paymentMethod = PaymentMethod::clientId($this->client()->id)
                                          ->whereContactId($this->contact()->id)
                                          ->firstOrFail();
        }


        $invoicRepo = app('App\Ninja\Repositories\InvoiceRepository');
        $invoicRepo->setGatewayFee($this->invoice(), $paymentMethod->payment_type->gateway_type_id);

        if ( ! $this->meetsGatewayTypeLimits($paymentMethod->payment_type->gateway_type_id)) {
            // The customer must have hacked the URL
            Session::flash('error', trans('texts.limits_not_met'));

            return redirect()->to('view/' . $this->invitation->invitation_key);
        }
        

        // prepare and process payment
        return $this->paymentDetails($paymentMethod);
    }

    protected function doCardConnectOnsitePurchase($paymentData)
    {
        //Send the payment for processing
        $request = new AuthorizationRequest($paymentData);
        $response = $this->cardconnect->authorize($request);

        if(!empty($response->respstat) && $response->respstat == 'A'){
            $ref = $response->retref;
            if(!empty($ref)){
                $payment = $this->createPayment($ref);
                return $payment;
            }else{
                throw new Exception('Payment method was charged successfully, however, an error occured when recording the payment against the invoice. Please contact us.');
            }
            
        }else{
            throw new Exception($response->resptext ?: trans('texts.payment_error'));
        }
        
    }

    public function removePaymentMethod($paymentMethod)
    {

        $profile_id = $paymentMethod->account_gateway_token->token;
        $account_id = $paymentMethod->source_reference;
        if(empty($account_id) || empty($profile_id)){
            throw new Exception("Error deleting payment method");
        }

        $profile = $this->cardconnect->deleteProfile($profile_id, $account_id);

        if(isset($profile) && (empty($profile['respstat']) || $profile['respstat'] == 'A')){
            $paymentMethod->delete();
            return true;
        }
        throw new Exception("Error deleting payment method");
    }

    protected function attemptVoidPayment($response, $payment, $amount)
    {
        if (! parent::attemptVoidPayment($response, $payment, $amount)) {
            return false;
        }

        $data = $response->getData();
        //look at more
        if ($data instanceof \Braintree\Result\Error) {
            $error = $data->errors->deepAll()[0];
            if ($error && $error->code == 91506) {
                return true;
            }
        }

        return false;
    }

    public function tokenize()
    {
        return true;
    }

    public function isValid()
    {
        try {
            $this->createTransactionToken();
            return true;
        } catch (Exception $exception) {
            return get_class($exception);
        }
    }

}
