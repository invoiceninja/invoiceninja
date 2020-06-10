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
use Omnipay\Omnipay;

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


    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        $this->company_gateway = $company_gateway;

        $this->invitation = $invitation;

        $this->client = $client;
    }

    
    public function authorize($payment_method) {}
    
    public function purchase() {}

    public function refund() {}
}
