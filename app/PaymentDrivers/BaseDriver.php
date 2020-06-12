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

use App\Models\Client;
use App\Models\CompanyGateway;
use App\PaymentDrivers\AbstractPaymentDriver;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SystemLogTrait;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class BaseDriver extends AbstractPaymentDriver
{
    use SystemLogTrait;
    use MakesHash;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    protected $invitation;

    /* Gateway capabilities */
    protected $refundable = false;

    /* Token billing */
    protected $token_billing = false;

    /* Authorise payment methods */
    protected $can_authorise_credit_card = false;

    /* The client */
    protected $client;

    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        $this->company_gateway = $company_gateway;

        $this->invitation = $invitation;

        $this->client = $client;
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     * @param  const $payment_method the GatewayType::constant
     * @return view                 Return a view for collecting payment method information
     */
    public function authorize($payment_method) {}
    
    /**
     * Executes purchase attempt for a given amount
     * 
     * @param  float   $amount                 The amount to be collected
     * @param  boolean $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed                          
     */
    public function purchase($amount, $return_client_response = false) {}

    /**
     * Executes a refund attempt for a given amount with a transaction_reference
     * 
     * @param  float   $amount                 The amount to be refunded
     * @param  string  $transaction_reference  The transaction reference
     * @param  boolean $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed                          
     */
    public function refund($amount, $transaction_reference, $return_client_response = false) {}

}
