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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Subscriptions\ShowPlanSwitchRequest;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $amount = $recurring_invoice->subscription
                                    ->service()
                                    ->calculateUpgradePrice($recurring_invoice, $target);
        /**
         * Null value here is a proxy for
         * denying the user a change plan option
         */
        if (is_null($amount)) {
            render('subscriptions.denied');
        }

        return render('subscriptions.switch', [
            'subscription' => $recurring_invoice->subscription,
            'recurring_invoice' => $recurring_invoice,
            'target' => $target,
            'amount' => $amount,
        ]);
    }
}
