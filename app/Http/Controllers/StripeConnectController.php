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

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\StripeConnect\InitializeStripeConnectRequest;
use App\Libraries\MultiDB;
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
        // Should we check if company has set country in the ap? Otherwise this will fail.

        if(!is_array($request->getTokenContent()))
            abort(400, 'Invalid token');


        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $data = [
            'type' => 'standard',
            'email' => $request->getContact()->email,
            'country' => $request->getCompany()->country()->iso_3166_2,
        ];

        $exists = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $request->getCompany()->id)
            ->first();

        if ($exists) {
            return render('gateways.stripe.connect.existing');
        }

        $account = Account::create($data);

        $link = Account::link($account->id, $token);

        $company_gateway = CompanyGatewayFactory::create($request->getCompany()->id, $request->getContact()->id);

        $company_gateway->fill([
            'gateway_key' => 'd14dd26a47cecc30fdd65700bfb67b34',
            'fees_and_limits' => [],
            'config' => encrypt(json_encode(['account_id' => $account->id]))
        ]);

        $company_gateway->save();

        return redirect($link['url']);
    }

    public function completed()
    {
        return render('gateways.stripe.connect.completed');
    }
}
