<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Peppol;

use App\Services\EDocument\Gateway\MutatorUtil;

/**
 * India - GSTIN e-invoicing.
 *
 * Uses IN:GSTIN as the primary identifier with Email routing.
 * CountrySubentity must be an ISO 3166-2:IN state/UT code.
 */
class IN extends BaseCountry
{
    public array $countrySubEntity = [
        'AN' => 'Andaman and Nicobar Islands',
        'AP' => 'Andhra Pradesh',
        'AR' => 'Arunachal Pradesh',
        'AS' => 'Assam',
        'BR' => 'Bihar',
        'CH' => 'Chandigarh',
        'CG' => 'Chhattisgarh',
        'DH' => 'Dadra and Nagar Haveli and Daman and Diu',
        'DL' => 'Delhi',
        'GA' => 'Goa',
        'GJ' => 'Gujarat',
        'HR' => 'Haryana',
        'HP' => 'Himachal Pradesh',
        'JK' => 'Jammu and Kashmir',
        'JH' => 'Jharkhand',
        'KA' => 'Karnataka',
        'KL' => 'Kerala',
        'LA' => 'Ladakh',
        'LD' => 'Lakshadweep',
        'MP' => 'Madhya Pradesh',
        'MH' => 'Maharashtra',
        'MN' => 'Manipur',
        'ML' => 'Meghalaya',
        'MZ' => 'Mizoram',
        'NL' => 'Nagaland',
        'OD' => 'Odisha',
        'PY' => 'Puducherry',
        'PB' => 'Punjab',
        'RJ' => 'Rajasthan',
        'SK' => 'Sikkim',
        'TN' => 'Tamil Nadu',
        'TS' => 'Telangana',
        'TR' => 'Tripura',
        'UP' => 'Uttar Pradesh',
        'UK' => 'Uttarakhand',
        'WB' => 'West Bengal',
    ];

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        // Resolve supplier state to ISO 3166-2:IN code
        $supplier_state = $mutator_util->getClientSetting('Invoice.AccountingSupplierParty.Party.PostalAddress.Address.CountrySubentity');
        $resolved_state = $this->getStateCode($supplier_state, $invoice, 'supplier');
        $p_invoice->AccountingSupplierParty->Party->PostalAddress->CountrySubentity = $resolved_state;

        return $p_invoice;
    }

    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        // non-IN sender, IN receiver — resolve customer state
        $client_state = $mutator_util->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CountrySubentity');
        $resolved_state = $this->getStateCode($client_state, $invoice);
        $p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;

        return $p_invoice;
    }

    /**
     * Resolve a state name or code to an ISO 3166-2:IN code.
     *
     * Accepts either an ISO code (e.g. "IN-KA") or a state name
     * (e.g. "Karnataka") and returns the ISO code. Case-insensitive.
     * Falls back to IN-DL (Delhi) if no match is found.
     */
    public function getStateCode(?string $state_code, mixed $invoice = null, string $party = 'client'): string
    {
        $state_code = strlen($state_code ?? '') > 1
            ? $state_code
            : ($invoice ? ($party === 'supplier' ? ($invoice->company->settings->state ?? '') : $invoice->client->state) : '');

        // Already a valid ISO code
        if (isset($this->countrySubEntity[$state_code])) {
            return $state_code;
        }

        // Exact name match
        $key = array_search($state_code, $this->countrySubEntity);

        if ($key !== false) {
            return $key;
        }

        // Case-insensitive match (handles diacritics and casing variations)
        $lower = mb_strtolower($state_code);

        foreach ($this->countrySubEntity as $code => $name) {
            if (mb_strtolower($name) === $lower) {
                return $code;
            }
        }

        // Partial match for common abbreviations (e.g. "Pondicherry" for Puducherry)
        $aliases = [
            'pondicherry' => 'PY',
            'orissa' => 'OD',
            'uttaranchal' => 'UK',
            'dadra and nagar haveli' => 'DH',
            'daman and diu' => 'DH',
        ];

        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        return 'DL';
    }
}
