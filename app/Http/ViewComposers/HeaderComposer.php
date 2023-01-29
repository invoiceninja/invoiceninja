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

namespace App\Http\ViewComposers;

use Illuminate\View\View;

/**
 * Class HeaderComposer.
 */
class HeaderComposer
{
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

    /**
     * @return array
     */
    private function headerData()
    {
        if (! auth()->user()) {
            return [];
        }

        $companies = auth()->user()->companies;

        //companies
        $data['current_company'] = $companies->first();
        $data['companies'] = $companies;
        /*
                $data['current_company'] = $companies->first(function ($company){
                    return $company->id == auth()->user()->company()->id;
                });

                $data['companies'] = $companies->reject(function ($company){
                    return $company->id == auth()->user()->company()->id;
                });
        */
        return $data;
    }
}
