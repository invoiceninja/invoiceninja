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

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;

class YodleeController extends BaseController
{

    public function auth(Request $request)
    {

        $yodlee = new Yodlee(true);

        $data = [
            'access_token' => $yodlee->getAccessToken('sbMem62e1e69547bfb1'),
            'fasttrack_url' => $yodlee->fast_track_url
        ];

        return view('bank.yodlee.auth', $data);

    }

}
