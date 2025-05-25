<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Payments\PaymentWebhookRequest;

class PaymentWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request)
    {
        //return early if we cannot resolve the company gateway
        $company_gateway = $request->getCompanyGateway();

        if (!$company_gateway) {
            return response()->json([], 200);
        }

        return $company_gateway
                ->driver()
                ->processWebhookRequest($request);
    }
}
