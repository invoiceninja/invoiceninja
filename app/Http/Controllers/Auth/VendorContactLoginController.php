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

namespace App\Http\Controllers\Auth;

use App\Events\Contact\ContactLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\ViewComposers\PortalComposer;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Route;

class VendorContactLoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/vendor/purchase_orders';

    public function __construct()
    {
        $this->middleware('guest:vendor', ['except' => ['logout']]);
    }

    public function catch()
    {
        $data = [

        ];

        return $this->render('purchase_orders.catch');
    }

    public function logout()
    {
        Auth::guard('vendor')->logout();
        request()->session()->invalidate();

        return redirect('/vendors');
    }
}
