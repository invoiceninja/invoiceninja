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

namespace App\Utils;

class TruthSource
{
    public $company;

    public $user;

    public $company_user;

    public $company_token;

    public function setCompanyUser($company_user)
    {
        $this->company_user = $company_user;

        return $this;
    }

    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    public function setCompany($company)
    {
        $this->company = $company;

        return $this;
    }

    public function setCompanyToken($company_token)
    {
        $this->company_token = $company_token;

        return $this;
    }

    public function getCompany()
    {
        return $this->company;
    }

    public function getCompanyUser()
    {
        return $this->company_user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getCompanyToken()
    {
        return $this->company_token;
    }
}
