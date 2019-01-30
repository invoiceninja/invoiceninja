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
     * @SWG\Property(property="name", type="string", example="Name")
     * @SWG\Property(property="token", type="string", example="Token")
     */

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
