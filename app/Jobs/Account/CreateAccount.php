<?php

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\User\CreateUser;
use App\Jobs\Company\CreateCompany;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;

class CreateAccount
{

    use Dispatchable;
    use UserSessionAttributes;

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
        /*
         * Create account
         */
        $account = Account::create($this->request->toArray());

        /*
         * Create company
         */
        $company = CreateCompany::dispatchNow($this->request, $account);

        /*
         * Set default company
         */
        $account->default_company_id = $company->id;
        $account->save();

        /*
         * Create user
         */
        $user = CreateUser::dispatchNow($this->request, $account, $company);

        /*
         * Set current company
         */
        $this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);

        /*
         * Login user
         */
        Auth::loginUsingId($user->id, true);

        /*
         * Fire related events
         */
        event(new AccountCreated($user));

        return $user;
    }
}
