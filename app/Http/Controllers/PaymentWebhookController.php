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

namespace App\Http\Controllers;

use App\Http\Requests\Payments\PaymentWebhookRequest;

class PaymentWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(PaymentWebhookRequest $request, string $company_key, string $gateway_key)
    {
        return response([], 200);
    }
}
