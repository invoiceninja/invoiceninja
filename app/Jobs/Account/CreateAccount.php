<?php

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\User\CreateUser;
use App\Jobs\Company\CreateCompany;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;

class CreateAccount
{

    use Dispatchable;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $account = Account::create($this->request->toArray());

        $company = CreateCompany::dispatchNow($this->request, $account);

        $account->default_company_id = $company->id;
        $account->save();

        $user = CreateUser::dispatchNow($this->request, $account, $company);

        Auth::loginUsingId($user->id, true);

        event(new AccountCreated($user));

        return $user;
    }
}
