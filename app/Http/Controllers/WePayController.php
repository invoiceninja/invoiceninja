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
use App\Models\CompanyGateway;
use App\Models\User;
use App\PaymentDrivers\WePayPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WePayController extends BaseController
{
    use MakesHash;
    /**
     * Initialize WePay Signup.
     */
    public function signup(string $token)
    {

        // $hash = [
        //     'user_id' => auth()->user()->id,
        //     'company_key'=> auth()->user()->company()->company_key,
        //     'context' => $request->input('context'),
        // ];

        $hash = Cache::get($token);

        //temporarily comment this out
        // if(!$hash)
        //     abort(400, 'Link expired');
        // MultiDB::findAndSetDbByCompanyKey($hash['company_key']);
        // $data['user_id'] = $this->encodePrimaryKey($hash['user_id']);
        // $data['company_key'] = $hash['company_key'];

        $user = User::first();
        $data['user_id'] = $user->id;

        $data['company_key'] = $user->account->companies()->first()->company_key;

        $wepay_driver = new WePayPaymentDriver(new CompanyGateway, null, null);

        return $wepay_driver->setup($data);

    }

    public function processSignup(Request $request)
    {

    }
}
