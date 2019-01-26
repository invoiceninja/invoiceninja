<?php

namespace App\Utils\Traits;


/**
 * Class UserSessionAttributes
 * @package App\Utils\Traits
 */
trait UserSessionAttributes
{

    /**
     * @param $value
     */
    public function setCurrentCompanyId($value) : void
    {
        session(['current_company_id' => $value]);
    }

    /**
     * @return int
     */
    public function getCurrentCompanyId() : int
    {
        return session('current_company_id');
    }

}
