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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Uploads\StoreUploadRequest;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class NinjaPlanController extends Controller
{

    public function index(string $contact_key, string $company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($company_key);
        $company = Company::where('company_key', $company_key)->first();

        nlog("Ninja Plan Controller Company key found {$company->company_key}");

        $account = $company->account;

        if (MultiDB::findAndSetDbByContactKey($contact_key) && $client_contact = ClientContact::where('contact_key', $contact_key)->first())
        {            
        
            nlog("Ninja Plan Controller - Found and set Client Contact");
            
            Auth::guard('contact')->login($client_contact,true);

            /* Current paid users get pushed straight to subscription overview page*/
            if($account->isPaidHostedClient())
                return redirect('/client/subscriptions');

            /* Users that are not paid get pushed to a custom purchase page */
            return $this->render('subscriptions.ninja_plan', ['settings' => $client_contact->company->settings]);
        }

        return redirect()->route('client.catchall');

    }
}
