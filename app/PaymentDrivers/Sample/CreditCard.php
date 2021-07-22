<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Eway;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{

    public $driver_class;

    public function __construct(PaymentDriver $driver_class)
    {
        $this->driver_class = $driver_class;
    }

    public function authorizeView($data)
    {

    }

    public function authorizeRequest($request)
    {

    }

    public function paymentView($data)
    {
    
    }

    public function processPaymentResponse($request)
    {
        
    }


    /* Helpers */

    /*
      You will need some helpers to handle successful and unsuccessful responses

      Some considerations after a succesful transaction include:

      Logging of events: success +/- failure
      Recording a payment 
      Notifications
     */


}