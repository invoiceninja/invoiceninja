<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Company;

/**
 * CompanyRepository.
 */
class CompanyRepository extends BaseRepository
{
    public function __construct()
    {
    }

    /**
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Company $company
     * @return Company|null  Company Object
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
