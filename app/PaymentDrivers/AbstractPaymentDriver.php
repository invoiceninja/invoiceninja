<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\Payment;

abstract class AbstractPaymentDriver
{
    abstract public function authorize($payment_method);

    abstract public function purchase($amount, $return_client_response = false);

    abstract public function refund(Payment $payment, $refund_amount, $return_client_response = false);

    abstract public function setPaymentMethod($payment_method_id);
}
