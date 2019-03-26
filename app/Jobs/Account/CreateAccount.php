<?php

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\User\CreateUser;
use App\Models\Account;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
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

    public function __construct(array $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?User
    {
        /*
         * Create account
         */
        $account = Account::create($this->request);

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
         * Create token
         */
        CreateCompanyToken::dispatchNow($company, $account);

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
