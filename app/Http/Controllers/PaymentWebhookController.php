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
use Illuminate\Support\Arr;

class PaymentWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(PaymentWebhookRequest $request, string $company_gateway_id, string $company_key)
    {
        $payment = $request->getPayment();

        return $request->getCompanyGateway()
            ->driver($payment->client)
            ->processWebhookRequest($request, $payment);
    }
}
