<?php

namespace App\Utils\Traits;


trait UserSessionAttributes
{

    public function setCurrentCompanyId($value)
    {
        session(['current_company_id' => $value]);
    }

    public function getCurrentCompanyId()
    {
        return session('current_company_id');
    }

}
