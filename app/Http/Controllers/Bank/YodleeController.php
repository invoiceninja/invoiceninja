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
use Illuminate\Http\Request;

class YodleeController extends BaseController
{

    public function auth(YodleeAuthRequest $request)
    {

        // create a user at this point 
        // use the one time token here to pull in the actual user

        //store the user_account_id on the accounts table

        $yodlee = new Yodlee();
        $yodlee->setTestMode();

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
        $yodlee->setTestMode();

        if(!is_string($token))
            dd($token);

        $data = [
            'access_token' => $yodlee->getAccessToken(),
            'fasttrack_url' => $yodlee->getFastTrackUrl(),
            'config_name' => 'testninja',
            'flow' => $flow,
            'company' => $company,
            'account' => $company->account,
        ];

        return view('bank.yodlee.auth', $data);

    }

}
