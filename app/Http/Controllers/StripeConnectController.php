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

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\StripeConnect\InitializeStripeConnectRequest;
use App\Libraries\MultiDB;
use App\Models\Client;
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

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $request->getCompany()->id)
            ->first();

        if ($company_gateway) {

            $config = decrypt($company_gateway->config);

            if(property_exists($config, 'account_id'))
                return render('gateways.stripe.connect.existing');
        
        }

        $account = Account::create($data);

        $link = Account::link($account->id, $token);

        if(!$company_gateway)
            $company_gateway = CompanyGatewayFactory::create($request->getCompany()->id, $request->getContact()->id);

        $company_gateway->fill([
            'gateway_key' => 'd14dd26a47cecc30fdd65700bfb67b34',
            'fees_and_limits' => [],
            'config' => encrypt(json_encode(['account_id' => $account->id]))
        ]);

        /* Set Credit Card To Enabled */
        $gateway_types = $company_gateway->driver(new Client)->gatewayTypes();

        $fees_and_limits = new \stdClass;
        $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

        $company_gateway->fees_and_limits = $fees_and_limits;
        $company_gateway->save();

        /* Link account if existing account exists */
        if($account_id = $this->checkAccountAlreadyLinkToEmail($company_gateway, $request->getContact()->email)) {
            
            $config = json_decode(decrypt($company_gateway->config));

            $config->account_id = $account_id;
            $company_gateway->config = encrypt(json_encode($config));
            $company_gateway->save();

            return render('gateways.stripe.connect.existing');
        }


        return redirect($link['url']);
    }

    public function completed()
    {
        return render('gateways.stripe.connect.completed');
    }


    private function checkAccountAlreadyLinkToEmail($company_gateway, $email)
    {
        $client = Client::first() ? Client::first() : new Client;

        //Pull the list of Stripe Accounts and see if we match
        $accounts = $company_gateway->driver($client)->getAllConnectedAccounts()->data;

        foreach($accounts as $account)
        {
            if($account['email'] == $email)
                return $account['id'];
        }

        return false;

    }
}
