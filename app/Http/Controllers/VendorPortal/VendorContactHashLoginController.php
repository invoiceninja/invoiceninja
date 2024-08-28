<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\VendorPortal;

use App\Http\Controllers\Controller;
use Auth;

class VendorContactHashLoginController extends Controller
{
    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $contact_key  The contact key
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(string $contact_key)
    {
        return redirect('/vendors/purchase_orders');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
    */

    public function magicLink(string $magic_link)
    {
        return redirect($this->setRedirectPath());
    }

    /**
     * errorPage
     *
     * @return \Illuminate\View\View
     */
    public function errorPage()
    {
        return render('generic.error', ['title' => session()->get('title'), 'notification' => session()->get('notification')]);
    }
}
