<?php namespace App\Ninja\Transformers;

use App\Models\AccountToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTokenTransformer extends TransformerAbstract
{

    public function transform(AccountToken $account_token)
    {
        return [
            'id' => (int) $account_token->id,
            'account_id' =>(int) $account_token->account_id,
            'user_id' => (int) $account_token->user_id,
            'public_id' => (int) $account_token->public_id,
            'name' => $account_token->name,
            'token' => $account_token->token
        ];
    }
}