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

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Checkout3dsController extends Controller
{
    public function index(Request $request)
    {
        return $request->all();
    }
}
