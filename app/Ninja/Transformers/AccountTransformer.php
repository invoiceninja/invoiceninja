<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\AccountToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'users',
        'clients',
    ];

    public function includeUsers($account)
    {
        return $this->collection($account->users, new UserTransformer);
    }

    public function includeClients($account)
    {
        return $this->collection($account->clients, new ClientTransformer);
    }

    public function transform(Account $account)
    {
        return [
            'account_key' => $account->account_key,
            'name' => $account->present()->name,
        ];
    }
}