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

namespace App\Http\Controllers;

use App\Libraries\MultiDB;
use App\Models\Company;
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
        // return render('gateways.wepay.signup.finished');

        $hash = Cache::get($token);

        MultiDB::findAndSetDbByCompanyKey($hash['company_key']);

        $user = User::findOrFail($hash['user_id']);

        $company = Company::where('company_key', $hash['company_key'])->firstOrFail();

        $data['user_id'] = $user->id;
        $data['company'] = $company;

        $wepay_driver = new WePayPaymentDriver(new CompanyGateway, null, null);

        return $wepay_driver->setup($data);
    }

    public function finished()
    {
        return render('gateways.wepay.signup.finished');
    }
}
