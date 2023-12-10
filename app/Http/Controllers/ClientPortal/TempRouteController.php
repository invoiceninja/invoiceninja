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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;

class TempRouteController extends Controller
{
    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $hash  The hash
     * @return Redirect
     */
    public function index(string $hash)
    {
        $data = [];
        $data['html'] = Cache::get($hash);

        return view('pdf.html', $data);
    }
}
