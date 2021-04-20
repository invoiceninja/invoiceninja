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

namespace App\Http\Controllers;

use App\Http\Requests\StripeConnect\InitializeStripeConnectRequest;
use App\Models\CompanyGateway;
use App\PaymentDrivers\Stripe\Connect\Account;
use Stripe\Exception\ApiErrorException;

class StripeConnectController extends BaseController
{
    /**
     * Initialize Stripe Connect flow.
     *
     * @param string $token One-time token
     * @throws ApiErrorException
     */
    public function initialize(InitializeStripeConnectRequest $request, string $token)
    {
//        $request->getTokenContent();

        $data = [
            'type' => 'standard',
            'email' => 'user@example.com',
            'country' => 'US',
        ];

        $account = Account::create($data);

        $link = Account::link($account->id);

        // Store account->id into company_gateways.

        return redirect($link['url']);
    }

    public function completed()
    {
        dd(request()->all());
    }
}
