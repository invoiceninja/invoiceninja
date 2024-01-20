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

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

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
        return $this->render('purchase_orders.catch');
    }

    public function logout()
    {
        Auth::guard('vendor')->logout();

        request()->session()->invalidate();

        return redirect('/vendors');
    }
}
