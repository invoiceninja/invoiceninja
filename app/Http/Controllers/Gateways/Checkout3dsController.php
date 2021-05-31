<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\Checkout3ds\Checkout3dsRequest;

class Checkout3dsController extends Controller
{
    public function index(Checkout3dsRequest $request, string $company_key, string $company_gateway_id, string $hash)
    {
        return $request->getCompanyGateway()
            ->driver($request->getClient())
            ->process3dsConfirmation($request);
    }
}
