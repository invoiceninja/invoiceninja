<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;

/**
 * Class CompanyTokenTransformer.
 */
class CompanyTokenTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];


    /**
     * @param CompanyToken $company_token
     *
     * @return array
     */
    public function transform(CompanyToken $company_token)
    {
        return [
            'id' => $this->encodePrimaryKey($company_token->id),
            'token' => $company_token->token,
            'name' => $company_token->name,
            'user_id' => $company_token->user_id,
            'company_id' => $company_token->company_id,
        ];
    }


}
