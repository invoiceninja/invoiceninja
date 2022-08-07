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
use Auth;
use Illuminate\Support\Facades\Cache;

class TempRouteController extends Controller
{
    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $contact_key  The contact key
     * @return Auth|Redirect
     */
    public function index(string $hash)
    {
        $data = [];
        $data['html'] = Cache::get($hash);

        return view('pdf.html', $data);
    }
}
