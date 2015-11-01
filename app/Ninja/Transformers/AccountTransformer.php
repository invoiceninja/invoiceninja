<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'users'
    ];

    public function includeUsers($account)
    {
        $users = $account->users;

        return $this->collection($users, new UserTransformer);
    }

    public function transform(Account $account)
    {
        return [
            'id' => (int) $account->id,
            'name' => $account->name,
        ];
    }
}