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

namespace App\Http\Controllers;

use App\Libraries\MultiDB;

class SubdomainController extends BaseController
{

    public function __construct()
    {

    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        $subdomain_exists = MultiDB::findAndSetDbByDomain(request()->input('subdomain'));

        if($subdomain_exists)
            return response()->json(['message' => 'Domain not available'] , 401);

        return response()->json(['message' => 'Domain available'], 200);
    }

}
