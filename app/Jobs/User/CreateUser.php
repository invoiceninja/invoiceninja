<?php

namespace App\Jobs\User;

use App\Events\User\UserCreated;
use App\Models\User;
use App\Models\CompanyUser;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;

class CreateUser
{
    use MakesHash;
    use Dispatchable;

    protected $request;

    protected $account;

    protected $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request, $account, $company)
    {
        $this->request = $request;
        $this->account = $account;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $user = new User();
        $user->account_id = $this->account->id;
        $user->password = bcrypt($this->request->input('password'));
        $user->accepted_terms_version = config('ninja.terms_version');
        $user->confirmation_code = $this->createDbHash(config('database.default'));
        $user->db = config('database.default');
        $user->fill($this->request->all());
        $user->save();

        $user->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
        ]);
/*
        CompanyUser::create([
            'user_id' => $user->id,
            'account_id' => $this->account->id,
            'company_id' => $this->company->id,
            'is_admin' => true,
            'is_owner' => true,
            'permissions' => '',

        ]);

*/
        event(new UserCreated($user));

        return $user;
    }
}
