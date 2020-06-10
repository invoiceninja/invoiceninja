<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\GatewayType;
use App\PaymentDrivers\CheckoutCom\Utilities;
use App\Utils\Traits\SystemLogTrait;

class CheckoutComPaymentDriver extends BasePaymentDriver
{
    use SystemLogTrait, Utilities;

    /* The company gateway instance*/
    public $company_gateway;
    
    /* The Invitation */
    protected $invitation;

    /* Gateway capabilities */
    protected $refundable = true;

    /* Token billing */
    protected $token_billing = true;

    /* Authorise payment methods */
    protected $can_authorise_credit_card = true;

    /** Instance of \Checkout\CheckoutApi */
    public $gateway;

    /** Since with Checkout.com we handle only credit cards, this method should be empty. */
    public function setPaymentMethod($string = null)
    {
        return $this;
    }

    public function init()
    {
        // $this->gateway 
    }

    public function viewForType($gateway_type_id)
    {
        if ($gateway_type_id == GatewayType::CREDIT_CARD) {
            return 'gateways.checkout.credit_card';
        }

        if ($gateway_type_id == GatewayType::TOKEN) {
            return 'gateways.checkout.credit_card';
        }
    }

    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this;
        $data['client'] = $this->client;
        $data['currency'] = $this->client->getCurrencyCode();
        $data['value'] = $data['amount_with_fee']; // Fix for currencies.
        $data['customer_email'] = $this->client->present()->email;

        return render($this->viewForType($data['payment_method_id']), $data);
    }

    public function processPaymentResponse($request)
    {
        dd($request->all());
    }
}
