<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
        if (!$request->getCompanyGateway()) {
            return response()->json([], 200);
        }

        return $request
            ->getCompanyGateway()
            ->driver()
            ->processWebhookRequest($request);
    }
}
