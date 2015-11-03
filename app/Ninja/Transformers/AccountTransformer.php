<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\AccountToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'users',
        'account_tokens'
    ];

    public function includeAccountTokens($account)
    {
        $account_token = AccountToken::whereAccountId($account->id)->whereName('ios_api_token')->first();

        return $this->collection($account_token, new AccountTokenTransformer);

    }
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