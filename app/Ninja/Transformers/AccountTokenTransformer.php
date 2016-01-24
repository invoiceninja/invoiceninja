<?php namespace App\Ninja\Transformers;

use App\Models\AccountToken;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class AccountTokenTransformer extends TransformerAbstract
{

    public function transform(AccountToken $account_token)
    {
        return [
            'name' => $account_token->name,
            'token' => $account_token->token
        ];
    }
}