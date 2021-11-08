<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HostedMigrationController extends Controller
{

    public function getAccount(Request $request)
    {

        if($request->header('X-API-HOSTED-SECRET') != config('ninja.ninja_hosted_secret'))
            return;

        if($user = MultiDB::hasUser(['email' => $request->input('email')]))
        {

            if($user->account->owner() && $user->account->companies()->count() >= 1)
            {
                return response()->json(['token' => $user->account->companies->first()->tokens->first()->token] ,200);
            }

            return response()->json(['error' => 'This user is not able to perform a migration. Please contact us at contact@invoiceninja.com to discuss.'], 401);
            
        }

        $account = CreateAccount::dispatchNow($request->all(), $request->getClientIp());

        $company = $account->companies->first();

        $company_token = CompanyToken::where('user_id', auth()->user()->id)
                                     ->where('company_id', $company->id)
                                     ->first();         

        return response()->json(['token' => $company_token->token], 200);

    }

    public function confirmForwarding(Request $request)
    {
        if($request->header('X-API-HOSTED-SECRET') != config('ninja.ninja_hosted_secret'))
            return;

        $input = $request->all();

        MultiDB::findAndSetDbByCompanyKey($input['account_key']);

        $company = Company::with('account')->where('company_key', $input['account_key'])->first();
        $account = $company->account;
        $client_id = false;

        if($contact = ClientContact::on('db-ninja-01')->where(['email' => $input['email'], 'company_id' => config('ninja.ninja_default_company_id')])->first()){
            $client_id = $contact->client_id;
        }
        else if($client = Client::on('db-ninja-01')->where(['custom_value2' => $account->key, 'company_id' => config('ninja.ninja_default_company_id')])->first()){
            $client_id = $client->id;
        }

        //get ninja client_id;
        
        if(strlen($input['gateway_reference']) >1 && $client_id){

            Artisan::call('ninja:add-token', [
                '--customer' => $input['gateway_reference'], '--client_id' => 1
            ]);

        }

        $forward_url = $company->domain();
         
        return response()->json(['forward_url' => $forward_url], 200);
    }

}