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

namespace App\PaymentDrivers\PayFast;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFastPaymentDriver;
use Illuminate\Support\Str;

class CreditCard
{

    public $payfast;

    public function __construct(PayFastPaymentDriver $payfast)
    {
        $this->payfast = $payfast;
    }

    public function authorizeView($data)
    {
        // $data['gateway'] = $this->wepay_payment_driver;
        
        // return render('gateways.wepay.authorize.authorize', $data);
    }

 	public function authorizeResponse($request)
 	{
   
    
 	}  




}

