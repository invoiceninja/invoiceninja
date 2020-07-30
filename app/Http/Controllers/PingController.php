<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Utils\SystemHealth;
use Illuminate\Http\Request;

class PingController extends BaseController
{
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(
        	['company_name' => auth()->user()->getCompany()->present()->name(),
        	 'user_name' => auth()->user()->present()->name(),
        	], 200);
    }

    public function health()
    {
        if(Ninja::isNinja())
            return response()->json(['message' => 'Route not available', 'errors'=>[]], 403);

        return response()->json(SystemHealth::check(),200);
    }
}
