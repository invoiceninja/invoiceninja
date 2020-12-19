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

namespace App\Http\Controllers;

use App\Http\Requests\Payments\PaymentWebhookRequest;

class PaymentWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request, string $company_key, string $company_gateway_id)
    {
        $payment = $request->getPayment();
        $client = is_null($payment) ? $request->getClient() : $payment->client;

        return $request->getCompanyGateway()
            ->driver($client)
            ->processWebhookRequest($request, $payment);
    }
}
