<?php

namespace App\Ninja\Transformers;

use App\Models\AccountToken;
use League\Fractal\TransformerAbstract;

/**
 * Class AccountTokenTransformer.
 */
class AccountTokenTransformer extends TransformerAbstract
{
    /**
     * @param AccountToken $account_token
     *
     * @return array
     */
    public function transform(AccountToken $account_token)
    {
        return [
            'name' => $account_token->name,
            'token' => $account_token->token,
        ];
    }
}
