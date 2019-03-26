<?php

namespace App\Jobs\User;

use App\DataMapper\DefaultSettings;
use App\Events\User\UserCreated;
use App\Models\CompanyUser;
use App\Models\User;
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

    public function __construct(array $request, $account, $company)
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
    public function handle() : ?User
    {

        $user = new User();
        $user->account_id = $this->account->id;
        $user->password = bcrypt($this->request['password']);
        $user->accepted_terms_version = config('ninja.terms_version');
        $user->confirmation_code = $this->createDbHash(config('database.default'));
        $user->db = config('database.default');
        $user->fill($this->request);
        $user->save();

        $user->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => json_encode([]),
            'settings' => json_encode(DefaultSettings::userSettings()),
        ]);

        event(new UserCreated($user));


        return $user;
    }
}
