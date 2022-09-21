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

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Yodlee\YodleeAuthRequest;
use App\Jobs\Bank\ProcessBankTransactions;
use App\Models\BankIntegration;
use Illuminate\Http\Request;

class YodleeController extends BaseController
{

    public function auth(YodleeAuthRequest $request)
    {

        // create a user at this point 
        // use the one time token here to pull in the actual user
        // store the user_account_id on the accounts table

        $yodlee = new Yodlee();

        $company = $request->getCompany();


        if($company->account->bank_integration_account_id){

            $flow = 'edit';

            $token = $company->account->bank_integration_account_id;

        }
        else{

            $flow = 'add';

            $response = $yodlee->createUser($company);

            $token = $response->user->loginName;

            $company->account->bank_integration_account_id = $token;

            $company->push();
            
        }
        
        $yodlee = new Yodlee($token);

        if($request->has('window_closed') && $request->input("window_closed") == "true")
            $this->getAccounts($company, $token);

        $data = [
            'access_token' => $yodlee->getAccessToken(),
            'fasttrack_url' => $yodlee->getFastTrackUrl(),
            'config_name' => config('ninja.yodlee.config_name'),
            'flow' => $flow,
            'company' => $company,
            'account' => $company->account,
            'completed' => $request->has('window_closed') ? true : false,
        ];

        return view('bank.yodlee.auth', $data);

    }

    private function getAccounts($company, $token)
    {
        $yodlee = new Yodlee($token);

        $accounts = $yodlee->getAccounts(); 

        foreach($accounts as $account)
        {

            if(!BankIntegration::where('bank_account_id', $account['id'])->where('company_id', $company->id)->exists())
            {
                $bank_integration = new BankIntegration();
                $bank_integration->company_id = $company->id;
                $bank_integration->account_id = $company->account_id;
                $bank_integration->user_id = $company->owner()->id;
                $bank_integration->bank_account_id = $account['id'];
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_status = $account['account_status'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_id = $account['provider_id'];
                $bank_integration->provider_name = $account['provider_name'];
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->balance = $account['current_balance'];
                $bank_integration->currency = $account['account_currency'];
                $bank_integration->from_date = now()->subYear();
                
                $bank_integration->save();
            }

        }


        $company->account->bank_integrations->each(function ($bank_integration) use ($company){
            
            ProcessBankTransactions::dispatch($company->account->bank_integration_account_id, $bank_integration);

        });


    }

}
