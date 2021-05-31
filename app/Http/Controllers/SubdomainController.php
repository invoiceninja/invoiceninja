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
    private $protected = [
        'www',
        'app',
        'ninja',
        'sentry',
        'staging',
        'pdf',
        'demo',
        'docs',
        'client_domain',
        'custom_domain',
    ];

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

        if(in_array(request()->input('subdomain'), $this->protected) || MultiDB::findAndSetDbByDomain(['subdomain' => request()->input('subdomain')]))
            return response()->json(['message' => 'Domain not available'] , 401);

        return response()->json(['message' => 'Domain available'], 200);
    }

}
