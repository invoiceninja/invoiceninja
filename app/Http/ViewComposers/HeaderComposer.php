<?php

namespace App\Http\ViewComposers;

use App\Utils\Traits\UserSessionAttributes;
use Illuminate\View\View;

class HeaderComposer
{
    use UserSessionAttributes;

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('header', $this->headerData());
    }

    private function headerData()
    {
        if(!auth()->user())
            return [];
        
        //companies
        $companies = auth()->user()->companies;

        $data['current_company'] = $companies->first(function ($company){
            return $company->id == $this->getCurrentCompanyId();
        });

        $data['companies'] = $companies->reject(function ($company){
            return $company->id == $this->getCurrentCompanyId();
        });

        return $data;
    }

}