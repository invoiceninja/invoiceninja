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
use Auth;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NinjaPlanController extends Controller
{

    public function index(string $contact_key, string $company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($company_key);
        $company = Company::where('company_key', $company_key)->first();
        $account = $company->account;

        if (Ninja::isHosted() && MultiDB::findAndSetDbByContactKey(request()->segment(3)) && $client_contact = ClientContact::where('contact_key', request()->segment(3))->first())
        {            
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
