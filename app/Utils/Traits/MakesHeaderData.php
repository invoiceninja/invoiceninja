<?php

namespace App\Utils\Traits;
use Illuminate\Support\Facades\Auth;


/**
 * Class MakesHash
 * @package App\Utils\Traits
 */
trait MakesHeaderData
{

    public function metaData()
    {
        //companies
        $companies = Auth::user()->companies;
dd(Auth::user());
        $data['current_company'] = $companies->first(function ($company){

                return $company->id == Auth::user()->current_company_id;
        });

        dd($data);
        $data['companies'] = $companies->forget($data['current_company']);


        return $data;
    }

}