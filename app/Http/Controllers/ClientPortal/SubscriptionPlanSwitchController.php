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
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionPlanSwitchController extends Controller
{
    /**
     * Show the page for switching between plans.
     *
     * @param ShowPlanSwitchRequest $request
     * @param Subscription $subscription
     * @param string $target_subscription
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(ShowPlanSwitchRequest $request, Subscription $subscription, Subscription $target_subscription)
    {
        return render('subscriptions.switch', [
            'subscription' => $subscription,
            'target_subscription' => $target_subscription,
        ]);
    }
}
