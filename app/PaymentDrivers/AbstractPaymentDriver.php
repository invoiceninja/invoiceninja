<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\Payment;
use App\Models\PaymentHash;
use Illuminate\Http\Request;

abstract class AbstractPaymentDriver
{
    abstract public function authorizeView(array $data);

    abstract public function authorizeResponse(\App\Http\Requests\Request | Request $request);

    abstract public function processPaymentView(array $data);

    abstract public function processPaymentResponse(Request $request);

    abstract public function refund(Payment $payment, $refund_amount, $return_client_response = false);

    abstract public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash);

    abstract public function setPaymentMethod($payment_method_id);
}
