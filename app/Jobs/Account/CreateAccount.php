<?php

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\User\CreateUser;
use App\Jobs\Company\CreateCompany;

use App\Models\UserCompany;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $user = CreateUser::dispatchNow($this->request, $account, $company);

        Auth::loginUsingId($user->id, true);

        event(new AccountCreated($user));

        return $user;
    }
}
