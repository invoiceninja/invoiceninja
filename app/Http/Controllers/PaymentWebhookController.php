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
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(PaymentWebhookRequest $request, string $gateway_key, string $company_key)
    {
        return $request->getCompanyGateway()
            ->driver($request->getClient())
            ->processWebhookRequest($request, $request->getPayment());
    }
}
