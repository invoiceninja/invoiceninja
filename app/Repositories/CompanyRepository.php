<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * CompanyRepository.
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
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Company $company
     * @return     Client|Company|null  Company Object
     */
    public function save(array $data, Company $company) : ?Company
    {
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = $this->parseCustomFields($data['custom_fields']);
        }

        $company->fill($data);

        if (array_key_exists('settings', $data)) {
            $company->saveSettings($data['settings'], $company);
        }

        $company->save();

        return $company;
    }

    private function parseCustomFields($fields) :array
    {
        foreach ($fields as &$value) {
            $value = (string) $value;
        }

        return $fields;
    }
}
