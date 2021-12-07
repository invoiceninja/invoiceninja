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
use App\Models\RecurringInvoice;
use Auth;

class ContactHashLoginController extends Controller
{

    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $contact_key  The contact key
     * @return Auth|Redirect
     */
    public function login(string $contact_key)
    {
        if(request()->has('subscription') && request()->subscription == 'true') {

            $recurring_invoice = RecurringInvoice::where('client_id', auth()->guard('contact')->client->id)
                                                 ->whereNotNull('subscription_id')
                                                 ->whereNull('deleted_at')
                                                 ->first();

            return redirect()->route('client.recurring_invoice.show', $recurring_invoice->hashed_id);

        }

        return redirect('/client/invoices');
    }

    public function magicLink(string $magic_link)
    {
        return redirect('/client/invoices');
    }

    public function errorPage()
    {
        return render('generic.error', ['title' => session()->get('title'), 'notification' => session()->get('notification')]);
    }
}
