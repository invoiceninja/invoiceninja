<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\RecurringInvoices\ShowRecurringInvoiceRequest;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;

class SubscriptionController extends Controller
{
    /**
     * This function is used to display the subscription page.
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */

    public function index()
    {
        // if (Ninja::isHosted()) {
        //     $count = RecurringInvoice::query()
        //         ->where('client_id', auth()->guard('contact')->user()->client->id)
        //         ->where('company_id', auth()->guard('contact')->user()->client->company_id)
        //         ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
        //         ->where('is_deleted', 0)
        //         ->whereNotNull('subscription_id')
        //         ->withTrashed()
        //         ->count();

        //     if ($count == 0) {
        //         return redirect()->route('client.ninja_contact_login', ['contact_key' => auth()->guard('contact')->user()->contact_key, 'company_key' => auth()->guard('contact')->user()->company->company_key]);
        //     }
        // }

        return render('subscriptions.index');
    }

    /**
     * Display the recurring invoice.
     *
     * @param ShowRecurringInvoiceRequest $request
     * @param RecurringInvoice $recurring_invoice
     */
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->render('subscriptions.show', [
            'invoice' => $recurring_invoice->load('invoices', 'subscription'),
            'subscription' => $recurring_invoice->subscription
        ]);
    }
}
