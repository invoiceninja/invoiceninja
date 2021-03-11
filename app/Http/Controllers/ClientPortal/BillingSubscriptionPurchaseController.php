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
use App\Models\BillingSubscription;
use Illuminate\Http\Request;

class BillingSubscriptionPurchaseController extends Controller
{
    public function index(BillingSubscription $billing_subscription)
    {
        return view('billing-portal.purchase');
    }
}
