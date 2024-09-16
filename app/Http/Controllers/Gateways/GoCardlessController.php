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

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\GoCardless\IbpRequest;
use App\Models\GatewayType;

class GoCardlessController extends Controller
{
    public function ibpRedirect(IbpRequest $request)
    {

        return $request
            ->getCompanyGateway()
            ->driver($request->getClient())
            ->setPaymentMethod(GatewayType::INSTANT_BANK_PAY)
            ->processPaymentResponse($request);
    }
}
