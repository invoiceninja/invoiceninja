<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;

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

}
    

