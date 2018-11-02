<?php

namespace App\Utils\Traits;
use Illuminate\Support\Facades\Auth;


/**
 * Class MakesHash
 * @package App\Utils\Traits
 */
trait MakesHeaderData
{

    use UserSessionAttributes;

    public function headerData()
    {
        //companies
        $companies = Auth::user()->companies;

        $data['current_company'] = $companies->first(function ($company){
            return $company->id == $this->getCurrentCompanyId();
        });

        $data['companies'] = $companies->reject(function ($company){
            return $company->id == $this->getCurrentCompanyId();
        });

        return $data;
    }

}