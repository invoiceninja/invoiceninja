<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Utils\Traits\MakesHash;

class CompanyFactory
{
	use MakesHash;

	public static function create(int $account_id) :Company
	{

        $company = new Company;
        $company->name = '';
        $company->account_id = $account_id;
        $company->company_key = $this->createHash();
        $company->settings = CompanySettings::defaults();
        $company->db = config('database.default');
        $company->domain = '';
        
        return $company;
        
    }
}