<?php

namespace App\Jobs\User;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;

class CreateUser
{

    use Dispatchable;

    protected $request;

    protected $account;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request, $account)
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

        $user = new User();
        $user->account_id = $this->account->id;
        $user->password = Hash::make($this->request->input('password'));
        $user->accepted_terms_version = config('ninja.terms_version');
        $user->confirmation_code = strtolower(str_random(RANDOM_KEY_LENGTH));
        $user->db = config('database.default');
        $user->fill($this->request->all());
        $user->save();


        return $user;
    }
}
