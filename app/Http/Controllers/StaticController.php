<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Account\CreateAccountRequest;
use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Statics;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;

class StaticController extends BaseController
{

    public function __invoke()
    {
    
        $response = Statics::company(auth()->user()->getCompany()->getLocale());
        
        return response()->json($response, 200, ['Content-type'=> 'application/json; charset=utf-8'], JSON_PRETTY_PRINT);

    }

}

