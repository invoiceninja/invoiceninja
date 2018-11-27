<?php

namespace App\Utils\Traits;


trait UserSessionAttributes
{

    public function setCurrentCompanyId($value) : void
    {
        session(['current_company_id' => $value]);
    }

    public function getCurrentCompanyId() : int
    {
        return session('current_company_id');
    }

}
