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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Subscriptions\ShowPlanSwitchRequest;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionPlanSwitchController extends Controller
{
    /**
     * Show the page for switching between plans.
     *
     * @param ShowPlanSwitchRequest $request
     * @param RecurringInvoice $recurring_invoice
     * @param string $target
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(ShowPlanSwitchRequest $request, RecurringInvoice $recurring_invoice, Subscription $target)
    {
        //calculate whether a payment is required or whether we pass through a credit for this.
        
        $amount = $recurring_invoice->subscription->service()->calculateUpgradePrice($recurring_invoice, $target);

nlog($amount);

        //if($amount == null)
        //please show account upgrade unavailable
        //@ben

        return render('subscriptions.switch', [
            'subscription' => $recurring_invoice->subscription,
            'recurring_invoice' => $recurring_invoice,
            'target' => $target,
            'amount' => $amount,
        ]);
    }
}
