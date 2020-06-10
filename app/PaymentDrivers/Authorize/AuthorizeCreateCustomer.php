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

namespace App\PaymentDrivers\Authorize;

use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class AuthorizeCreateCustomer
{

    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function create($data = null)
    {

    }

}