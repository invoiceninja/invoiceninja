<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Http\Requests\Account\CreateAccountRequest;
use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyUserTransformer;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MigrationController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function purgeCompany(Company $company)
    {
       
       $company->delete();
       
    }


}
