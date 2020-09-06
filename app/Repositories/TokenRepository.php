<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\CompanyToken;

class TokenRepository extends BaseRepository
{
    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return CompanyToken::class;
    }

    /**
     * Saves the companytoken.
     *
     * @param      array  $data    The data
     * @param      \App\Models\CompanyToken  $company_token  The company_token
     *
     * @return     CompanyToken|\App\Models\CompanyToken|null  CompanyToken Object
     */
    public function save(array $data, CompanyToken $company_token)
    {
        $company_token->fill($data);
        $company_token->is_system = false;

        $company_token->save();

        return $company_token;
    }
}
