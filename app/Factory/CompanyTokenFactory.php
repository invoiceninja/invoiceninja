<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\CompanyToken;
use Illuminate\Support\Str;

class CompanyTokenFactory
{
    public static function create(int $company_id, int $user_id, int $account_id): CompanyToken
    {
        $token = new CompanyToken();
        $token->user_id = $user_id;
        $token->account_id = $account_id;
        $token->token = Str::random(64);
        $token->name = '';
        $token->company_id = $company_id;

        return $token;
    }
}
