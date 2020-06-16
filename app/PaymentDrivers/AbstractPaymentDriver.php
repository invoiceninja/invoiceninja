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

abstract class AbstractPaymentDriver
{
    
    abstract public function authorize($payment_method);
    
    abstract public function purchase($amount, $return_client_response = false);
    
    abstract public function refund($amount, $transaction_reference, $return_client_response = false);

    abstract public function bootPaymentMethod();
   
}