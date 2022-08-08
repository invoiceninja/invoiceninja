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

namespace App\Http\Controllers;

use App\DataMapper\FeesAndLimits;
use App\Exceptions\SystemError;
use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\StripeConnect\InitializeStripeConnectRequest;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\Connect\Account;
use App\PaymentDrivers\Stripe\Jobs\StripeWebhook;
use Exception;
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

        if (! is_array($request->getTokenContent())) {
            abort(400, 'Invalid token');
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $company = Company::where('company_key', $request->getTokenContent()['company_key'])->first();

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $request->getCompany()->id)
            ->first();

        if ($company_gateway) {
            $config = $company_gateway->getConfig();

            if (property_exists($config, 'account_id') && strlen($config->account_id) > 1) {
                return view('auth.connect.existing');
            }
        }

        $stripe_client_id = config('ninja.ninja_stripe_client_id');
        $redirect_uri = 'https://invoicing.co/stripe/completed';
        $endpoint = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$stripe_client_id}&redirect_uri={$redirect_uri}&scope=read_write&state={$token}";

        return redirect($endpoint);
    }

    public function completed(InitializeStripeConnectRequest $request)
    {
        \Stripe\Stripe::setApiKey(config('ninja.ninja_stripe_key'));

        try {
            $response = \Stripe\OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $request->input('code'),
            ]);
        } catch (\Exception $e) {
            nlog($e->getMessage());
            throw new SystemError($e->getMessage(), 500);
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $company = Company::where('company_key', $request->getTokenContent()['company_key'])->first();

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $company->id)
            ->first();

        if (! $company_gateway) {
            $company_gateway = CompanyGatewayFactory::create($company->id, $company->owner()->id);
            $fees_and_limits = new \stdClass;
            $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits;
            $company_gateway->gateway_key = 'd14dd26a47cecc30fdd65700bfb67b34';
            $company_gateway->fees_and_limits = $fees_and_limits;
            $company_gateway->setConfig([]);
            $company_gateway->token_billing = 'always';
            // $company_gateway->save();
        }

        $payload = [
            'account_id' => $response->stripe_user_id,
            'token_type' => 'bearer',
            'stripe_publishable_key' => $response->stripe_publishable_key,
            'scope' => $response->scope,
            'livemode' => $response->livemode,
            'stripe_user_id' => $response->stripe_user_id,
            'refresh_token' => $response->refresh_token,
            'access_token' => $response->access_token,
            'appleDomainVerification' => '',
        ];

        $company_gateway->setConfig($payload);
        $company_gateway->save();

        // StripeWebhook::dispatch($company->company_key, $company_gateway->id);

        //response here
        return view('auth.connect.completed');
    }

    private function checkAccountAlreadyLinkToEmail($company_gateway, $email)
    {
        $client = Client::first() ? Client::first() : new Client;

        //Pull the list of Stripe Accounts and see if we match
        $accounts = $company_gateway->driver($client)->getAllConnectedAccounts()->data;

        foreach ($accounts as $account) {
            if ($account['email'] == $email) {
                return $account['id'];
            }
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
