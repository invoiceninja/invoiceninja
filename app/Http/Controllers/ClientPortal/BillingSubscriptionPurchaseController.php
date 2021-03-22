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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingSubscriptionPurchaseController extends Controller
{
    public function index(BillingSubscription $billing_subscription, Request $request)
    {
        if ($request->has('locale')) {
            $this->setLocale($request->query('locale'));
        }

        return view('billing-portal.purchase', [
            'billing_subscription' => $billing_subscription,
            'hash' => Str::uuid()->toString(),
            'request_data' => $request->all(),
        ]);
    }

    /**
     * Set locale for incoming request.
     *
     * @param string $locale
     */
    private function setLocale(string $locale): void
    {
        $record = DB::table('languages')->where('locale', $locale)->first();

        if ($record) {
            App::setLocale($record->locale);
        }
    }
}
