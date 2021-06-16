<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\WePay\ACH;
use App\PaymentDrivers\WePay\CreditCard;
use App\PaymentDrivers\WePay\Setup;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use WePay;

class WePayPaymentDriver extends BaseDriver
{
    use MakesHash;

    /* Does this gateway support refunds? */
    public $refundable = true; 

    /* Does this gateway support token billing? */
    public $token_billing = true; 

    /* Does this gateway support authorizations? */
    public $can_authorise_credit_card = true; 

    /* Initialized gateway */
    public $wepay; 

    /* Initialized payment method */
    public $payment_method; 

    /* Maps the Payment Gateway Type - to its implementation */
    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_WEPAY; 

    public function init()
    {
        
        if (WePay::getEnvironment() == 'none') {
            
            if(config('ninja.wepay.environment') == 'staging')
                WePay::useStaging(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));
            else
                WePay::useProduction(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));

        }

        if ($this->company_gateway) 
            $this->wepay = new WePay($this->company_gateway->getConfigField('accessToken'));

        $this->wepay = new WePay(null);
        
        return $this;
        
    }

    /**
     * Return the gateway types that have been enabled
     * 
     * @return array
     */
    public function gatewayTypes(): array
    {
        $types = [];

        if($this->company_gateway->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled)
            $types[] = GatewayType::CREDIT_CARD;

        if($this->company_gateway->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled)
            $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    /**
     * Setup the gateway
     * 
     * @param  array $data user_id + company
     * @return view
     */
    public function setup(array $data)
    {
        return (new Setup($this))->boot($data);
    }

    /**
     * Set the payment method
     * 
     * @param int $payment_method_id Alias of GatewayType
     */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        $this->init();

        $data['gateway'] = $this->wepay;
        $client = $data['client'];
        $contact = $client->primary_contact()->first() ? $client->primary_contact()->first() : $lient->contacts->first();
        $data['contact'] = $contact;
        // $data['contact'] = $this->company_gateway
        // $data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
        // $data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        $this->init();
        
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function verificationView(ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->verificationView($cgt);
    }

    public function processVerification(Request $request, ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->processVerification($request, $cgt);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return $this->payment_method->yourRefundImplementationHere(); //this is your custom implementation from here
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation(); //this is your custom implementation from here
    }




    public function getClientRequiredFields(): array
    {
        $fields = [
            ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required'],
        ];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_name) {
            $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_email) {
            $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_address_line_2', 'label' => ctrans('texts.address2'), 'type' => 'text', 'validation' => 'nullable'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_shipping_address_line_2', 'label' => ctrans('texts.shipping_address2'), 'type' => 'text', 'validation' => 'sometimes'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }








}
