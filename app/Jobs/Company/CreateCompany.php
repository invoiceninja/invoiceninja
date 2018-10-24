<?php

namespace App\Jobs\Company;

use App\Events\Company\CompanyCreated;
use App\Events\UserSignedUp;
use App\Jobs\Account\CreateAccount;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Support\Facades\Hash;

class CreateCompany
{

    use Dispatchable;

    protected $request;

    protected $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request, $account = false)
    {
        $this->request = $request;
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $company = new Company();
        $company->name = $this->request->first_name . ' ' . $this->request->last_name;
        $company->account_id = $this->account->id;
        $company->company_key = strtolower(str_random(RANDOM_KEY_LENGTH));
        $company->ip = $this->request->ip();
        $company->save();


        return $company;
    }
}
