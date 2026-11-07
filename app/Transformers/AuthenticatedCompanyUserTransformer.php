<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\User;
use App\Models\CompanyToken;
use App\Models\CompanyUser;

class AuthenticatedCompanyUserTransformer extends CompanyUserTransformer
{
    public function __construct($serializer = null, ?User $user = null, private ?int $active_company_id = null)
    {
        parent::__construct($serializer, $user);
    }

    public function includeCompany(CompanyUser $company_user)
    {
        $transformer = new CompanyTransformer($this->serializer, $this->user);

        if ($this->active_company_id !== null && $company_user->company_id != $this->active_company_id) {
            $transformer->setDefaultIncludes([]);
            $transformer->setAvailableIncludes([]);
        }

        return $this->includeItem($company_user->company, $transformer, Company::class);
    }

    public function includeToken(CompanyUser $company_user)
    {
        $token = $company_user->tokens()
                              ->where('company_id', $company_user->company_id)
                              ->where('user_id', $company_user->user_id)
                              ->where('is_system', 1)
                              ->first();

        $transformer = $company_user->user_id === auth()->user()->id ? new CompanyTokenTransformer($this->serializer, $this->user) : new CompanyTokenHashedTransformer($this->serializer, $this->user);

        return $this->includeItem($token, $transformer, CompanyToken::class);
    }
}
