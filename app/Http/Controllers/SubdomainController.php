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

namespace App\Http\Controllers;

use App\Libraries\MultiDB;

class SubdomainController extends BaseController
{
    public function __construct()
    {
    }

    /**
     * Return if a subdomain is available.
     *
     */
    public function index()
    {
        if (!MultiDB::checkDomainAvailable(request()->input('subdomain'))) {
            return response()->json(['message' => ctrans('texts.subdomain_is_not_available')], 401);
        }


        if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?$/', request()->input('subdomain'))) {
            return response()->json(['message' => ctrans('texts.subdomain_is_not_available')], 401);
        }


        return response()->json(['message' => 'Domain available'], 200);
    }
}
