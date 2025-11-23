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

namespace App\Services\Company;

use App\Factory\TaxRateFactory;
use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function __construct(public Company $company)
    {
    }

    public function localizeCompany(User $user)
    {
        try {

            $taxes = [];

            switch ($this->company->settings->country_id) {
                case '32':  // Argentina
                    $taxes[] = ['name' => 'IVA', 'rate' => 21];
                    break;
                case '36':  // Australia
                    $taxes[] = ['name' => 'GST', 'rate' => 10];
                    break;
                case '40':  // Austria
                    $taxes[] = ['name' => 'USt', 'rate' => 20];
                    break;
                case '56':  // Belgium
                    $taxes[] = ['name' => 'BTW', 'rate' => 21];
                    break;
                case '76':  // Brazil
                    $taxes[] = ['name' => 'ICMS', 'rate' => 18];
                    break;
                case '100': // Bulgaria
                    $taxes[] = ['name' => 'ДДС', 'rate' => 20];
                    break;
                case '124': // Canada
                    $taxes[] = ['name' => 'GST', 'rate' => 5];
                    $taxes[] = ['name' => 'QST', 'rate' => 9.975];
                    $taxes[] = ['name' => 'HST', 'rate' => 13];
                    break;
                case '152': // Chile
                    $taxes[] = ['name' => 'IVA', 'rate' => 19];
                    break;
                case '170': // Colombia
                    $taxes[] = ['name' => 'IVA', 'rate' => 19];
                    break;
                case '191': // Croatia
                    $taxes[] = ['name' => 'PDV', 'rate' => 25];
                    break;
                case '196': // Cyprus
                    $taxes[] = ['name' => 'ΦΠΑ', 'rate' => 19];
                    break;
                case '203': // Czech Republic
                    $taxes[] = ['name' => 'DPH', 'rate' => 21];
                    break;
                case '208': // Denmark
                    $taxes[] = ['name' => 'moms', 'rate' => 25];
                    break;
                case '233': // Estonia
                    $taxes[] = ['name' => 'KM', 'rate' => 20];
                    break;
                case '246': // Finland
                    $taxes[] = ['name' => 'ALV', 'rate' => 25.5];
                    break;
                case '250': // France
                    $taxes[] = ['name' => 'TVA', 'rate' => 20];
                    break;
                case '276': // Germany
                    $taxes[] = ['name' => 'MwSt', 'rate' => 19];
                    break;
                case '300': // Greece
                    $taxes[] = ['name' => 'ΦΠΑ', 'rate' => 24];
                    break;
                case '348': // Hungary
                    $taxes[] = ['name' => 'ÁFA', 'rate' => 27];
                    break;
                case '352': // Iceland
                    $taxes[] = ['name' => 'VSK', 'rate' => 24];
                    break;
                case '356': // India
                    $taxes[] = ['name' => 'GST', 'rate' => 18];
                    break;
                case '360': // Indonesia
                    $taxes[] = ['name' => 'PPN', 'rate' => 11];
                    break;
                case '372': // Ireland
                    $taxes[] = ['name' => 'VAT', 'rate' => 23];
                    break;
                case '376': // Israel
                    $taxes[] = ['name' => 'מע"מ', 'rate' => 17];
                    break;
                case '380': // Italy
                    $taxes[] = ['name' => 'IVA', 'rate' => 22];
                    break;
                case '428': // Latvia
                    $taxes[] = ['name' => 'PVN', 'rate' => 21];
                    break;
                case '440': // Lithuania
                    $taxes[] = ['name' => 'PVM', 'rate' => 21];
                    break;
                case '442': // Luxembourg
                    $taxes[] = ['name' => 'TVA', 'rate' => 17];
                    break;
                case '458': // Malaysia
                    $taxes[] = ['name' => 'SST', 'rate' => 10];
                    break;
                case '470': // Malta
                    $taxes[] = ['name' => 'VAT', 'rate' => 18];
                    break;
                case '484': // Mexico
                    $taxes[] = ['name' => 'IVA', 'rate' => 16];
                    break;
                case '528': // Netherlands
                    $taxes[] = ['name' => 'BTW', 'rate' => 21];
                    break;
                case '554': // New Zealand
                    $taxes[] = ['name' => 'GST', 'rate' => 15];
                    break;
                case '578': // Norway
                    $taxes[] = ['name' => 'mva', 'rate' => 25];
                    break;
                case '604': // Peru
                    $taxes[] = ['name' => 'IGV', 'rate' => 18];
                    break;
                case '608': // Philippines
                    $taxes[] = ['name' => 'VAT', 'rate' => 12];
                    break;
                case '616': // Poland
                    $taxes[] = ['name' => 'VAT', 'rate' => 23];
                    break;
                case '620': // Portugal
                    $taxes[] = ['name' => 'IVA', 'rate' => 23];
                    break;
                case '642': // Romania
                    $taxes[] = ['name' => 'TVA', 'rate' => 19];
                    break;
                case '682': // Saudi Arabia
                    $taxes[] = ['name' => 'VAT', 'rate' => 15];
                    break;
                case '702': // Singapore
                    $taxes[] = ['name' => 'GST', 'rate' => 9];
                    break;
                case '703': // Slovakia
                    $taxes[] = ['name' => 'DPH', 'rate' => 20];
                    break;
                case '705': // Slovenia
                    $taxes[] = ['name' => 'DDV', 'rate' => 22];
                    break;
                case '710': // South Africa
                    $taxes[] = ['name' => 'VAT', 'rate' => 15];
                    break;
                case '724': // Spain
                    $taxes[] = ['name' => 'IVA', 'rate' => 21];
                    break;
                case '752': // Sweden
                    $taxes[] = ['name' => 'moms', 'rate' => 25];
                    break;
                case '756': // Switzerland
                    $taxes[] = ['name' => 'TVA', 'rate' => 7.7];
                    break;
                case '764': // Thailand
                    $taxes[] = ['name' => 'VAT', 'rate' => 7];
                    break;
                case '784': // United Arab Emirates
                    $taxes[] = ['name' => 'VAT', 'rate' => 5];
                    break;
                case '792': // Turkey
                    $taxes[] = ['name' => 'KDV', 'rate' => 20];
                    break;
                case '826': // United Kingdom
                    $taxes[] = ['name' => 'VAT', 'rate' => 20];
                    break;

                default:
                    return;
            }

            foreach ($taxes as $tax) {
                $tax_rate = TaxRateFactory::create($this->company->id, $user->id);
                $tax_rate->fill($tax);
                $tax_rate->save();
            }

        } catch (\Exception $e) {
            nlog("Exception:: CompanyService::" . $e->getMessage());
            nlog($e->getMessage());
        }

    }

}
