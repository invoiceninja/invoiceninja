<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

/**
 * Transforms QuickBooks IPPCompanyInfo into Invoice Ninja company data.
 *
 * QB fields: CompanyName, LegalName, CompanyAddr, LegalAddr, CustomerCommunicationAddr,
 * Email, CustomerCommunicationEmailAddr, PrimaryPhone, WebAddr, CompanyURL,
 * Country, DefaultTimeZone.
 */
class CompanyTransformer extends BaseTransformer
{
    /**
     * Transform QuickBooks company info to Ninja structure.
     *
     * @param  mixed  $qb_data  QuickBooksOnline\API\Data\IPPCompanyInfo (or array)
     * @return array{quickbooks: array, settings: array}
     */
    public function qbToNinja(mixed $qb_data): array
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb(): void
    {
        // Reserved for Ninja → QB sync when needed.
    }

    /**
     * @param  mixed  $data  IPPCompanyInfo object or array
     * @return array{quickbooks: array<string, mixed>, settings: array<string, mixed>}
     */
    public function transform(mixed $data): array
    {
        $addr = $this->pickAddress($data);
        $country_raw = data_get($addr, 'Country') ?? data_get($addr, 'CountryCode') ?? data_get($data, 'Country');
        $country_id = $this->resolveCountry($country_raw);

        $quickbooks = [
            'companyName' => data_get($data, 'CompanyName', '') ?: data_get($data, 'LegalName', ''),
        ];

        $settings = [
            'address1' => data_get($addr, 'Line1', ''),
            'address2' => data_get($addr, 'Line2', ''),
            'city' => data_get($addr, 'City', ''),
            'state' => data_get($addr, 'CountrySubDivisionCode', ''),
            'postal_code' => data_get($addr, 'PostalCode', ''),
            'country_id' => $country_id,
            'phone' => $this->pickPhone($data),
            'email' => $this->pickEmail($data),
            'website' => data_get($data, 'WebAddr', '') ?: data_get($data, 'CompanyURL', ''),
            'timezone_id' => $this->resolveTimezone(data_get($data, 'DefaultTimeZone')),
        ];

        return [
            'quickbooks' => $quickbooks,
            'settings' => $settings,
        ];
    }

    /**
     * Prefer CompanyAddr, then LegalAddr, then CustomerCommunicationAddr.
     *
     * @param  mixed  $data
     * @return object|array|null
     */
    private function pickAddress(mixed $data)
    {
        $addr = data_get($data, 'CompanyAddr') ?? data_get($data, 'LegalAddr') ?? data_get($data, 'CustomerCommunicationAddr');

        return is_object($addr) ? $addr : (is_array($addr) ? $addr : []);
    }

    private function pickPhone(mixed $data): string
    {
        $phone = data_get($data, 'PrimaryPhone.FreeFormNumber');

        return is_string($phone) ? $phone : '';
    }

    private function pickEmail(mixed $data): string
    {
        $email = data_get($data, 'Email.Address') ?? data_get($data, 'CustomerCommunicationEmailAddr.Address') ?? data_get($data, 'CompanyEmailAddr');

        return is_string($email) ? $email : '';
    }
}
