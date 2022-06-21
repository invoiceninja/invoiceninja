<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\Checkout3ds\Checkout3dsRequest;

class Checkout3dsController extends Controller
{
    public function index(Checkout3dsRequest $request, string $company_key, string $company_gateway_id, string $hash)
    {
        if (! $request->getCompany()) {
            return response()->json(['message' => 'Company record not found.', 'company_key' => $company_key]);
        }

        if (! $request->getCompanyGateway()) {
            return response()->json(['message' => 'Company gateway record not found.', 'company_gateway_id' => $company_gateway_id]);
        }

        if (! $request->getPaymentHash()) {
            return response()->json(['message' => 'Hash record not found.', 'hash' => $hash]);
        }

        if (! $request->getClient()) {
            return response()->json(['message' => 'Client record not found.']);
        }

        return $request->getCompanyGateway()
            ->driver($request->getClient())
            ->process3dsConfirmation($request);
    }
}
