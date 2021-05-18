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
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\Connect\Account;
use Illuminate\Http\Request;
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

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $request->getCompany()->id)
            ->first();

        if ($company_gateway) {

            $config = decrypt($company_gateway->config);

            if(property_exists($config, 'account_id'))
                return view('auth.connect.existing');

        }

        $stripe_client_id = config('ninja.ninja_stripe_client_id');
        $redirect_uri = 'http://ninja.test:8000/stripe/completed';
        $endpoint = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$stripe_client_id}&redirect_uri={$redirect_uri}&scope=read_write&state={$token}";

        return redirect($endpoint);
    }

    public function completed(InitializeStripeConnectRequest $request)
    {

        \Stripe\Stripe::setApiKey(config('ninja.ninja_stripe_key'));

        $response = \Stripe\OAuth::token([
          'grant_type' => 'authorization_code',
          'code' => $request->input('code'),
        ]);

        $company = Company::where('company_key', $request->getTokenContent()['company_key'])->first();

        $company_gateway = CompanyGatewayFactory::create($company->id, $company->owner()->id);
        $fees_and_limits = new \stdClass;
        $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits;
        $company_gateway->gateway_key = 'd14dd26a47cecc30fdd65700bfb67b34';
        $company_gateway->fees_and_limits = $fees_and_limits;
        $company_gateway->config = encrypt(json_encode([]));
        $company_gateway->save();

        $payload = [
            'account_id' => $response->stripe_user_id,
            "token_type" => 'bearer',
            "stripe_publishable_key" => $request->input('stripe_publishable_key'),
            "scope" => $request->input('scope'),
            "livemode" => $request->input('livemode'),
            "stripe_user_id" => $request->input('stripe_user_id'),
            "refresh_token" => $request->input('refresh_token'),
            "access_token" => $request->input('access_token')
        ];

        /* Link account if existing account exists */
        if($account_id = $this->checkAccountAlreadyLinkToEmail($company_gateway, $request->getContact()->email)) {
            
            $config = json_decode(decrypt($company_gateway->config));

            $company_gateway->config = encrypt(json_encode($payload));
            $company_gateway->save();

            return view('auth.connect.existing');

        }

        $company_gateway->config = encrypt(json_encode($payload));
        $company_gateway->save();

        auth()->logout();
        //response here
        return view('auth.connect.completed');
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
    



    /*********************************
    * Stripe OAuth
    */
   
   //  public function initialize(InitializeStripeConnectRequest $request, string $token)
   // {

   //  $stripe_key = config('ninja.ninja_stripe_key');

   //  $endpoint = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$stripe_key}&scope=read_write";

   //  return redirect($endpoint);

   // }
}
