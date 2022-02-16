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
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class NinjaPlanController extends Controller
{
    use MakesHash;

    public function index(string $contact_key, string $account_or_company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($account_or_company_key);
        $company = Company::where('company_key', $account_or_company_key)->first();

        if(!$company){
            MultiDB::findAndSetDbByAccountKey($account_or_company_key);
            $account = Account::where('key', $account_or_company_key)->first();
        }
        else
            $account = $company->account;

        if (MultiDB::findAndSetDbByContactKey($contact_key) && $client_contact = ClientContact::where('contact_key', $contact_key)->first())
        {            
        
            nlog("Ninja Plan Controller - Found and set Client Contact");
            
            Auth::guard('contact')->loginUsingId($client_contact->id,true);

            // /* Current paid users get pushed straight to subscription overview page*/
            // if($account->isPaidHostedClient())
            //     return redirect('/client/dashboard');

            // /* Users that are not paid get pushed to a custom purchase page */
            // return $this->render('subscriptions.ninja_plan', ['settings' => $client_contact->company->settings]);

            return $this->plan();
            
        }

        return redirect()->route('client.catchall');

    }

    public function plan()
    {
        //harvest the current plan
        $data = [];
        $data['late_invoice'] = false;
        
        if(MultiDB::findAndSetDbByAccountKey(Auth::guard('contact')->user()->client->custom_value2))
        {
            $account = Account::where('key', Auth::guard('contact')->user()->client->custom_value2)->first();

            if($account)
            {

                if(Carbon::parse($account->plan_expires)->lt(now())){
                    //expired get the most recent invoice for payment

                    $late_invoice = Invoice::on('db-ninja-01')
                                           ->where('company_id', Auth::guard('contact')->user()->company->id)
                                           ->where('client_id', Auth::guard('contact')->user()->client->id)
                                           ->where('status_id', Invoice::STATUS_SENT)
                                           ->whereNotNull('subscription_id')
                                           ->orderBy('id', 'DESC')
                                           ->first();

                    //account status means user cannot perform upgrades until they pay their account.
                    // $data['late_invoice'] = $late_invoice;

                   //14-01-2022 remove late invoices from blocking upgrades
                       $data['late_invoice'] = false;

                }

                $recurring_invoice =  RecurringInvoice::on('db-ninja-01')
                                            ->where('client_id', auth()->guard('contact')->user()->client->id)
                                            ->where('company_id', Auth::guard('contact')->user()->company->id)
                                            ->whereNotNull('subscription_id')
                                            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                            ->orderBy('id', 'desc')
                                            ->first();

                $monthly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 6)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $yearly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 31)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $monthly_plans = $monthly_plans->merge($yearly_plans);

                $current_subscription_id = $recurring_invoice ? $this->encodePrimaryKey($recurring_invoice->subscription_id) : false;

                //remove existing subscription
                if($current_subscription_id){
                
                    $monthly_plans = $monthly_plans->filter(function ($plan) use($current_subscription_id){
                        return (string)$plan->hashed_id != (string)$current_subscription_id;
                    });   
                
                }

                $data['account'] = $account;
                $data['client'] =  Auth::guard('contact')->user()->client;
                $data['plans'] = $monthly_plans;
                $data['current_subscription_id'] = $current_subscription_id;
                $data['current_recurring_id'] = $recurring_invoice ? $recurring_invoice->hashed_id : false;

                return $this->render('plan.index', $data);

            }

        }
        else
            return redirect('/client/dashboard');
            
    }
}
