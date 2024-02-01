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

namespace App\Utils\Traits;

/**
 * Class MakesHeaderData.
 */
trait MakesHeaderData
{
    use UserSessionAttributes;

    /**
     * @return array
     */
    public function headerData(): array
    {
        //companies
        $companies = auth()->user()->companies;

        $data['current_company'] = $companies->first(function ($company) {
            return $company->id == auth()->user()->company()->id;
        });

        $data['companies'] = $companies->reject(function ($company) {
            return $company->id == auth()->user()->company()->id;
        });

        return $data;
    }
}
