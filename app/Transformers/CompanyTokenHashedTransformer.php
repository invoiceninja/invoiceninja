<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;

/**
 * Class CompanyTokenHashedTransformer.
 */
class CompanyTokenHashedTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
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
            'user_id' => $this->encodePrimaryKey($company_token->user_id),
            'token' => substr($company_token->token, 0, 10).'xxxxxxxxxxx',
            'name' => $company_token->name ?: '',
            'is_system' => (bool) $company_token->is_system,
            'updated_at' => (int) $company_token->updated_at,
            'archived_at' => (int) $company_token->deleted_at,
            'created_at' => (int) $company_token->created_at,
            'is_deleted' => (bool) $company_token->is_deleted,
        ];
    }
}
