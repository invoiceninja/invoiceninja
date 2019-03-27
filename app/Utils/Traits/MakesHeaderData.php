<?php

namespace App\Utils\Traits;


/**
 * Class MakesHeaderData
 * @package App\Utils\Traits
 */
trait MakesHeaderData
{

    use UserSessionAttributes;

    /**
     * @return array
     */
    public function headerData() : array
    {
        //companies
        $companies = auth()->user()->companies;

        $data['current_company'] = $companies->first(function ($company){
            return $company->id == auth()->user()->company()->id;
        });

        $data['companies'] = $companies->reject(function ($company){
            return $company->id == auth()->user()->company()->id;
        });

        return $data;
    }

}