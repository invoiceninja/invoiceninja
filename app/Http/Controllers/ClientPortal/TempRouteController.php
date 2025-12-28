<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class TempRouteController extends Controller
{
    /**
     * Logs a user into the client portal using their contact_key
     * @param  string $hash  The hash
     * @return \Illuminate\View\View
     */
    public function index(string $hash)
    {
        $data = [];
        $data['html'] = Cache::get($hash);

        return view('pdf.html', $data);
    }
}
