<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\CompanyToken;

class TokenRepository extends BaseRepository
{
    /**
     * Saves the companytoken.
     *
     * @param      array  $data    The data
     * @param CompanyToken $company_token  The company_token
     *
     * @return     CompanyToken|null  CompanyToken Object
     */
    public function save(array $data, CompanyToken $company_token)
    {
        $company_token->fill($data);
        $company_token->is_system = false;

        $company_token->save();

        return $company_token;
    }
}
