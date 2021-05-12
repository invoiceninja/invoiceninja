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
        return redirect('/client/login');
    }

    public function magicLink(string $magic_link)
    {
        return redirect('/client/login');
    }

    public function errorPage()
    {
        return render('generic.error', ['title' => session()->get('title'), 'notification' => session()->get('notification')]);
    }
}
