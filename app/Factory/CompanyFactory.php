<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\DataMapper\CompanySettings;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class CompanyFactory
{
    use MakesHash;

    /**
     * @param int $account_id
     * @return Company
     */
    public function create(int $account_id) :Company
    {
        $company = new Company;
        // $company->name = '';
        $company->account_id = $account_id;
        $company->company_key = $this->createHash();
        $company->settings = CompanySettings::defaults();
        $company->db = config('database.default');
        //$company->custom_fields = (object) ['invoice1' => '1', 'invoice2' => '2', 'client1'=>'3'];
        $company->custom_fields = (object) [];

        if(Ninja::isHosted())
            $company->subdomain = MultiDB::randomSubdomainGenerator();
        else 
            $company->subdomain = '';
        
        $company->enabled_modules = config('ninja.enabled_modules'); //32767;//8191; //4095
        $company->default_password_timeout = 1800000;


        return $company;
    }
}
