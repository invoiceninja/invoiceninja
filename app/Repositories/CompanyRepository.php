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

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * CompanyRepository
 */
class CompanyRepository extends BaseRepository
{

    public function __construct()
    {

    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {

        return Company::class;

    }

	/**
     * Saves the client and its contacts
     *
     * @param      array                           $data    The data
     * @param      \App\Models\Company              $client  The Company
     *
     * @return     Client|\App\Models\Company|null  Company Object
     */
    public function save(array $data, Company $company) : ?Company
	{

        $company->fill($data);

        $company->save();

        return $company;
        
	}

}