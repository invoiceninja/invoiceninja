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

use App\Models\Invoice;
use App\Services\EDocument\Gateway\MutatorUtil;

class RO extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["G+B", "", "RO:VAT", "RO:VAT"];
    }

    public array $countrySubEntity = [
        'RO-AB' => 'Alba',
        'RO-AG' => 'Argeș',
        'RO-AR' => 'Arad',
        'RO-B' => 'Bucharest',
        'RO-BC' => 'Bacău',
        'RO-BH' => 'Bihor',
        'RO-BN' => 'Bistrița-Năsăud',
        'RO-BR' => 'Brăila',
        'RO-BT' => 'Botoșani',
        'RO-BV' => 'Brașov',
        'RO-BZ' => 'Buzău',
        'RO-CJ' => 'Cluj',
        'RO-CL' => 'Călărași',
        'RO-CS' => 'Caraș-Severin',
        'RO-CT' => 'Constanța',
        'RO-CV' => 'Covasna',
        'RO-DB' => 'Dâmbovița',
        'RO-DJ' => 'Dolj',
        'RO-GJ' => 'Gorj',
        'RO-GL' => 'Galați',
        'RO-GR' => 'Giurgiu',
        'RO-HD' => 'Hunedoara',
        'RO-HR' => 'Harghita',
        'RO-IF' => 'Ilfov',
        'RO-IL' => 'Ialomița',
        'RO-IS' => 'Iași',
        'RO-MH' => 'Mehedinți',
        'RO-MM' => 'Maramureș',
        'RO-MS' => 'Mureș',
        'RO-NT' => 'Neamț',
        'RO-OT' => 'Olt',
        'RO-PH' => 'Prahova',
        'RO-SB' => 'Sibiu',
        'RO-SJ' => 'Sălaj',
        'RO-SM' => 'Satu Mare',
        'RO-SV' => 'Suceava',
        'RO-TL' => 'Tulcea',
        'RO-TM' => 'Timiș',
        'RO-TR' => 'Teleorman',
        'RO-VL' => 'Vâlcea',
        'RO-VN' => 'Vaslui',
        'RO-VS' => 'Vrancea',
    ];

    protected array $sectorList = [
        'SECTOR1' => 'Agriculture',
        'SECTOR2' => 'Manufacturing',
        'SECTOR3' => 'Tourism',
        'SECTOR4' => 'Information Technology (IT):',
        'SECTOR5' => 'Energy',
        'SECTOR6' => 'Healthcare',
        'SECTOR7' => 'Education',
    ];

    protected array $sectorCodes = [
        'RO-AB'  => 'Manufacturing, Agriculture',
        'RO-AG'  => 'Manufacturing, Agriculture',
        'RO-AR'  => 'Manufacturing, Agriculture',
        'RO-B'  => 'Information Technology (IT), Education, Tourism',
        'RO-BC'  => 'Manufacturing, Agriculture',
        'RO-BH'  => 'Agriculture, Manufacturing',
        'RO-BN'  => 'Agriculture',
        'RO-BR'  => 'Agriculture',
        'RO-BT'  => 'Agriculture',
        'RO-BV'  => 'Tourism, Agriculture',
        'RO-BZ'  => 'Agriculture',
        'RO-CJ'  => 'Information Technology (IT), Education, Tourism',
        'RO-CL'  => 'Agriculture',
        'RO-CS'  => 'Manufacturing, Agriculture',
        'RO-CT'  => 'Tourism, Agriculture',
        'RO-CV'  => 'Agriculture',
        'RO-DB'  => 'Agriculture',
        'RO-DJ'  => 'Agriculture',
        'RO-GJ'  => 'Manufacturing, Agriculture',
        'RO-GL'  => 'Energy, Manufacturing',
        'RO-GR'  => 'Agriculture',
        'RO-HD'  => 'Energy, Manufacturing',
        'RO-HR'  => 'Agriculture',
        'RO-IF'  => 'Information Technology (IT), Education',
        'RO-IL'  => 'Agriculture',
        'RO-IS'  => 'Information Technology (IT), Education, Agriculture',
        'RO-MH'  => 'Manufacturing, Agriculture',
        'RO-MM'  => 'Agriculture',
        'RO-MS'  => 'Energy, Manufacturing, Agriculture',
        'RO-NT'  => 'Agriculture',
        'RO-OT'  => 'Agriculture',
        'RO-PH'  => 'Energy, Manufacturing',
        'RO-SB'  => 'Manufacturing, Agriculture',
        'RO-SJ'  => 'Agriculture',
        'RO-SM'  => 'Agriculture',
        'RO-SV'  => 'Agriculture',
        'RO-TL'  => 'Agriculture',
        'RO-TM'  => 'Agriculture, Manufacturing',
        'RO-TR'  => 'Agriculture',
        'RO-VL'  => 'Agriculture',
        'RO-VN'  => 'Agriculture',
        'RO-VS'  => 'Agriculture',
    ];

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Enable RO-ANAF network
        $storecove_meta = $this->mergeMeta($storecove_meta, ["networks" => [
            [
                "application" => "ro-anaf",
                "settings" => [
                    "enabled" => true,
                ],
            ],
        ]]);

        // Set VAT routing
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'RO:VAT', "id" => $invoice->client->vat_number],
        ]));

        // Resolve state and sector codes
        $client_state = $mutator_util->getClientSetting('Invoice.AccountingSupplierParty.Party.PostalAddress.Address.CountrySubentity');
        $client_city = $mutator_util->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CityName');

        $resolved_state = $this->getStateCode($client_state, $invoice);
        $resolved_city = $this->getSectorCode($client_city, $invoice);

        $p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;
        $p_invoice->AccountingCustomerParty->Party->PostalAddress->CityName = $resolved_city;

        // Sort PartyIdentification by null values
        $query = $p_invoice->AccountingSupplierParty->Party->PartyIdentification;
        usort($query, function ($a, $b) {
            if ($a->value === null && $b->value !== null) return -1; //@phpstan-ignore-line
            if ($a->value !== null && $b->value === null) return 1; //@phpstan-ignore-line
            return 0;
        });
        $p_invoice->AccountingSupplierParty->Party->PartyIdentification = $query;

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    public function getStateCode(?string $state_code, mixed $invoice = null): string
    {
        $state_code = strlen($state_code ?? '') > 1 ? $state_code : ($invoice ? $invoice->client->state : '');

        // Codes are configured by default
        if (isset($this->countrySubEntity[$state_code])) {
            return $state_code;
        }

        $key = array_search($state_code, $this->countrySubEntity);

        if ($key !== false) {
            return $key;
        }

        return 'RO-B';
    }

    public function getSectorCode(?string $client_city, mixed $invoice = null): string
    {
        $client_sector_code = $client_city ?? ($invoice ? $invoice->client->city : '');
        $client_state = $invoice ? $invoice->client->state : '';

        if (in_array($this->getStateCode($client_state, $invoice), ['BUCHAREST', 'RO-B'])) {
            return in_array(strtoupper($invoice->client->city ?? ''), array_keys($this->sectorList)) ? strtoupper($invoice->client->city ?? '') : 'SECTOR1';
        }

        return $client_sector_code;
    }
}
