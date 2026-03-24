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

namespace App\Console\Commands;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Country;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePeppolTestData extends Command
{
    use MakesHash;

    protected $signature = 'ninja:create-peppol-test-data {country : ISO 3166-2 country code (e.g. DE, FR, IT)}';

    protected $description = 'Scaffold a Peppol-ready company with test clients (business, government, individual) for domestic and cross-border scenarios';

    /**
     * Country-specific defaults for realistic Peppol test data.
     *
     * @return array<string, array{vat: string, id_number: string, tax_rate: float, tax_name: string, city: string, state: string, postal_code: string, currency: string, address1: string, routing_id?: string, gov_id?: string, individual_id?: string, individual_vat?: string}>
     */
    private function countryDefaults(): array
    {
        return [
            // ── EU countries ──
            'AD' => [
                'vat' => 'ADA123456B', 'id_number' => 'A123456B', 'tax_rate' => 4.5, 'tax_name' => 'IGI',
                'city' => 'Andorra la Vella', 'state' => 'Andorra la Vella', 'postal_code' => 'AD500', 'currency' => '3',
                'address1' => 'Avinguda Meritxell 1',
                'gov_id' => 'GOV-AD-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'AL' => [
                'vat' => 'ALK12345678A', 'id_number' => 'K12345678A', 'tax_rate' => 20, 'tax_name' => 'TVSH',
                'city' => 'Tirana', 'state' => 'Tirana', 'postal_code' => '1001', 'currency' => '3',
                'address1' => 'Bulevardi Deshmoret e Kombit 1',
                'gov_id' => 'GOV-AL-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'AT' => [
                'vat' => 'ATU92335648', 'id_number' => '92335648', 'tax_rate' => 20, 'tax_name' => 'USt',
                'city' => 'Vienna', 'state' => 'Vienna', 'postal_code' => '1010', 'currency' => '3',
                'address1' => 'Stephansplatz 1',
                'gov_id' => 'GOV-AT-001', 'individual_id' => 'IND-AT-12345', 'individual_vat' => '',
            ],
            'BA' => [
                'vat' => 'BA123456789012', 'id_number' => '123456789012', 'tax_rate' => 17, 'tax_name' => 'PDV',
                'city' => 'Sarajevo', 'state' => 'Sarajevo Canton', 'postal_code' => '71000', 'currency' => '99',
                'address1' => 'Marsala Tita 1',
                'gov_id' => 'GOV-BA-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'BE' => [
                'vat' => 'BE1000000417', 'id_number' => '1000000417', 'tax_rate' => 21, 'tax_name' => 'BTW',
                'city' => 'Brussels', 'state' => 'Brussels', 'postal_code' => '1000', 'currency' => '3',
                'address1' => 'Grand Place 1',
                'gov_id' => '0987654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'BG' => [
                'vat' => 'BG123456789', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'DDS',
                'city' => 'Sofia', 'state' => 'Sofia City', 'postal_code' => '1000', 'currency' => '39',
                'address1' => 'Vitosha Boulevard 1',
                'gov_id' => 'GOV-BG-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'CY' => [
                'vat' => 'CY12345678X', 'id_number' => '12345678X', 'tax_rate' => 19, 'tax_name' => 'FPA',
                'city' => 'Nicosia', 'state' => 'Nicosia', 'postal_code' => '1010', 'currency' => '3',
                'address1' => 'Makarios Avenue 1',
                'gov_id' => 'GOV-CY-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'CZ' => [
                'vat' => 'CZ12345678', 'id_number' => '12345678', 'tax_rate' => 21, 'tax_name' => 'DPH',
                'city' => 'Prague', 'state' => 'Prague', 'postal_code' => '110 00', 'currency' => '51',
                'address1' => 'Vaclavske namesti 1',
                'gov_id' => 'GOV-CZ-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'DE' => [
                'vat' => 'DE973356489', 'id_number' => '973356489', 'tax_rate' => 19, 'tax_name' => 'VAT',
                'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10115', 'currency' => '3',
                'address1' => 'Unter den Linden 1',
                'gov_id' => 'LWID-DE-99001', 'individual_id' => 'STNR-12345678', 'individual_vat' => '',
            ],
            'DK' => [
                'vat' => 'DK12345678', 'id_number' => '12345678', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Copenhagen', 'state' => 'Capital Region', 'postal_code' => '1050', 'currency' => '20',
                'address1' => 'Stroget 1',
                'gov_id' => '87654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'EE' => [
                'vat' => 'EE123456789', 'id_number' => '12345678', 'tax_rate' => 22, 'tax_name' => 'KM',
                'city' => 'Tallinn', 'state' => 'Harju', 'postal_code' => '10111', 'currency' => '3',
                'address1' => 'Viru 1',
                'gov_id' => '87654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'ES' => [
                'vat' => 'ESB12345678', 'id_number' => 'B12345678', 'tax_rate' => 21, 'tax_name' => 'IVA',
                'city' => 'Madrid', 'state' => 'Madrid', 'postal_code' => '28001', 'currency' => '3',
                'address1' => 'Gran Via 1',
                'gov_id' => 'S2800001A', 'individual_id' => '12345678Z', 'individual_vat' => '',
            ],
            'FI' => [
                'vat' => 'FI12345678', 'id_number' => '1234567-8', 'tax_rate' => 25.5, 'tax_name' => 'ALV',
                'city' => 'Helsinki', 'state' => 'Uusimaa', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Mannerheimintie 1',
                'gov_id' => '0245437-2', 'individual_id' => '', 'individual_vat' => '',
            ],
            'FR' => [
                'vat' => 'FR82345678911', 'id_number' => '82345678911', 'tax_rate' => 20, 'tax_name' => 'TVA',
                'city' => 'Paris', 'state' => 'Ile-de-France', 'postal_code' => '75001', 'currency' => '3',
                'address1' => 'Rue de Rivoli 1',
                'gov_id' => '12345678901234', 'individual_id' => '', 'individual_vat' => '',
            ],
            'GR' => [
                'vat' => 'EL123456789', 'id_number' => '123456789', 'tax_rate' => 24, 'tax_name' => 'FPA',
                'city' => 'Athens', 'state' => 'Attica', 'postal_code' => '105 57', 'currency' => '3',
                'address1' => 'Ermou 1',
                'gov_id' => 'GOV-GR-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'HR' => [
                'vat' => 'HR12345678901', 'id_number' => '12345678901', 'tax_rate' => 25, 'tax_name' => 'PDV',
                'city' => 'Zagreb', 'state' => 'Zagreb', 'postal_code' => '10000', 'currency' => '3',
                'address1' => 'Ilica 1',
                'gov_id' => 'GOV-HR-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'HU' => [
                'vat' => 'HU12345678', 'id_number' => '12345678', 'tax_rate' => 27, 'tax_name' => 'AFA',
                'city' => 'Budapest', 'state' => 'Budapest', 'postal_code' => '1011', 'currency' => '69',
                'address1' => 'Andrassy ut 1',
                'gov_id' => 'GOV-HU-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'IE' => [
                'vat' => 'IE1234567WA', 'id_number' => '1234567WA', 'tax_rate' => 23, 'tax_name' => 'VAT',
                'city' => 'Dublin', 'state' => 'Dublin', 'postal_code' => 'D01 F5P2', 'currency' => '3',
                'address1' => "O'Connell Street 1",
                'gov_id' => 'GOV-IE-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'IT' => [
                'vat' => 'IT92443356489', 'id_number' => '92443356489', 'tax_rate' => 22, 'tax_name' => 'IVA',
                'city' => 'Rome', 'state' => 'Lazio', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Via del Corso 1', 'routing_id' => 'SCSCSCS',
                'gov_id' => '92443356489', 'individual_id' => 'RSSMRA85M01H501Z', 'individual_vat' => 'RSSMRA85M01H501Z',
            ],
            'LT' => [
                'vat' => 'LT123456789', 'id_number' => '1234567', 'tax_rate' => 21, 'tax_name' => 'PVM',
                'city' => 'Vilnius', 'state' => 'Vilnius', 'postal_code' => 'LT-01100', 'currency' => '3',
                'address1' => 'Gedimino pr. 1',
                'gov_id' => '7654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'LU' => [
                'vat' => 'LU12345678', 'id_number' => '12345678901', 'tax_rate' => 17, 'tax_name' => 'TVA',
                'city' => 'Luxembourg', 'state' => 'Luxembourg', 'postal_code' => 'L-1148', 'currency' => '3',
                'address1' => 'Rue du Fosse 1',
                'gov_id' => '98765432101', 'individual_id' => '', 'individual_vat' => '',
            ],
            'LV' => [
                'vat' => 'LV12345678901', 'id_number' => '12345678901', 'tax_rate' => 21, 'tax_name' => 'PVN',
                'city' => 'Riga', 'state' => 'Riga', 'postal_code' => 'LV-1050', 'currency' => '3',
                'address1' => 'Brivibas iela 1',
                'gov_id' => 'GOV-LV-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'MC' => [
                'vat' => 'FR12345678901', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'TVA',
                'city' => 'Monaco', 'state' => 'Monaco', 'postal_code' => '98000', 'currency' => '3',
                'address1' => 'Avenue de Monte-Carlo 1',
                'gov_id' => 'GOV-MC-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'ME' => [
                'vat' => 'ME12345678', 'id_number' => '12345678', 'tax_rate' => 21, 'tax_name' => 'PDV',
                'city' => 'Podgorica', 'state' => 'Podgorica', 'postal_code' => '81000', 'currency' => '3',
                'address1' => 'Slobode 1',
                'gov_id' => 'GOV-ME-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'MK' => [
                'vat' => 'MK1234567890123', 'id_number' => '1234567890123', 'tax_rate' => 18, 'tax_name' => 'DDV',
                'city' => 'Skopje', 'state' => 'Skopje', 'postal_code' => '1000', 'currency' => '91',
                'address1' => 'Macedonia Street 1',
                'gov_id' => 'GOV-MK-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'MT' => [
                'vat' => 'MT12345678', 'id_number' => '12345678', 'tax_rate' => 18, 'tax_name' => 'VAT',
                'city' => 'Valletta', 'state' => 'Valletta', 'postal_code' => 'VLT 1000', 'currency' => '3',
                'address1' => 'Republic Street 1',
                'gov_id' => 'GOV-MT-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'NL' => [
                'vat' => 'NL123456789B01', 'id_number' => '12345678', 'tax_rate' => 21, 'tax_name' => 'BTW',
                'city' => 'Amsterdam', 'state' => 'North Holland', 'postal_code' => '1012', 'currency' => '3',
                'address1' => 'Dam 1',
                'gov_id' => '00000001234567891234', 'individual_id' => '', 'individual_vat' => '',
            ],
            'PL' => [
                'vat' => 'PL1234567890', 'id_number' => '1234567890', 'tax_rate' => 23, 'tax_name' => 'VAT',
                'city' => 'Warsaw', 'state' => 'Masovia', 'postal_code' => '00-001', 'currency' => '3',
                'address1' => 'Nowy Swiat 1',
                'gov_id' => '9876543210', 'individual_id' => '', 'individual_vat' => '',
            ],
            'PT' => [
                'vat' => 'PT123456789', 'id_number' => '123456789', 'tax_rate' => 23, 'tax_name' => 'IVA',
                'city' => 'Lisbon', 'state' => 'Lisbon', 'postal_code' => '1100-001', 'currency' => '3',
                'address1' => 'Rua Augusta 1',
                'gov_id' => 'GOV-PT-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'RO' => [
                'vat' => 'RO010105019', 'id_number' => '010105019', 'tax_rate' => 19, 'tax_name' => 'TVA',
                'city' => 'SECTOR1', 'state' => 'RO-B', 'postal_code' => '010001', 'currency' => '3',
                'address1' => 'Calea Victoriei 1',
                'gov_id' => 'RO87654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'RS' => [
                'vat' => 'RS123456789', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'PDV',
                'city' => 'Belgrade', 'state' => 'Belgrade', 'postal_code' => '11000', 'currency' => '95',
                'address1' => 'Knez Mihailova 1',
                'gov_id' => 'GOV-RS-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'SE' => [
                'vat' => 'SE123456789101', 'id_number' => '1234567891', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Stockholm', 'state' => 'Stockholm', 'postal_code' => '111 57', 'currency' => '41',
                'address1' => 'Drottninggatan 1',
                'gov_id' => '9876543210', 'individual_id' => '', 'individual_vat' => '',
            ],
            'SI' => [
                'vat' => 'SI12345678', 'id_number' => '12345678', 'tax_rate' => 22, 'tax_name' => 'DDV',
                'city' => 'Ljubljana', 'state' => 'Ljubljana', 'postal_code' => '1000', 'currency' => '3',
                'address1' => 'Presernov trg 1',
                'gov_id' => 'GOV-SI-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'SK' => [
                'vat' => 'SK1234567890', 'id_number' => '1234567890', 'tax_rate' => 20, 'tax_name' => 'DPH',
                'city' => 'Bratislava', 'state' => 'Bratislava', 'postal_code' => '811 01', 'currency' => '3',
                'address1' => 'Hviezdoslavovo namestie 1',
                'gov_id' => 'GOV-SK-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'SM' => [
                'vat' => 'SM12345', 'id_number' => '12345', 'tax_rate' => 17, 'tax_name' => 'IGC',
                'city' => 'San Marino', 'state' => 'San Marino', 'postal_code' => '47890', 'currency' => '3',
                'address1' => 'Contrada del Collegio 1',
                'gov_id' => 'GOV-SM-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'TR' => [
                'vat' => 'TR1234567890', 'id_number' => '1234567890', 'tax_rate' => 20, 'tax_name' => 'KDV',
                'city' => 'Istanbul', 'state' => 'Istanbul', 'postal_code' => '34110', 'currency' => '41',
                'address1' => 'Istiklal Caddesi 1',
                'gov_id' => 'GOV-TR-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'VA' => [
                'vat' => 'VA12345678901', 'id_number' => '12345678901', 'tax_rate' => 0, 'tax_name' => 'VAT',
                'city' => 'Vatican City', 'state' => 'Vatican City', 'postal_code' => '00120', 'currency' => '3',
                'address1' => 'Via della Conciliazione 1',
                'gov_id' => 'GOV-VA-001', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── EFTA ──
            'CH' => [
                'vat' => 'CHE923356489MWST', 'id_number' => 'CHE923356489', 'tax_rate' => 8.1, 'tax_name' => 'MWST',
                'city' => 'Zurich', 'state' => 'ZH', 'postal_code' => '8001', 'currency' => '17',
                'address1' => 'Bahnhofstrasse 1',
                'gov_id' => 'CHE987654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'IS' => [
                'vat' => 'IS123456', 'id_number' => '1234567890', 'tax_rate' => 24, 'tax_name' => 'VSK',
                'city' => 'Reykjavik', 'state' => 'Capital Region', 'postal_code' => '101', 'currency' => '63',
                'address1' => 'Laugavegur 1',
                'gov_id' => '9876543210', 'individual_id' => '', 'individual_vat' => '',
            ],
            'LI' => [
                'vat' => 'LI12345', 'id_number' => '12345', 'tax_rate' => 8.1, 'tax_name' => 'MWST',
                'city' => 'Vaduz', 'state' => 'Vaduz', 'postal_code' => '9490', 'currency' => '17',
                'address1' => 'Stadtle 1',
                'gov_id' => 'GOV-LI-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'NO' => [
                'vat' => 'NO123456789MVA', 'id_number' => '123456789', 'tax_rate' => 25, 'tax_name' => 'MVA',
                'city' => 'Oslo', 'state' => 'Oslo', 'postal_code' => '0151', 'currency' => '14',
                'address1' => 'Karl Johans gate 1',
                'gov_id' => '987654321', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── UK ──
            'GB' => [
                'vat' => 'GB123456789', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'VAT',
                'city' => 'London', 'state' => 'London', 'postal_code' => 'EC1A 1BB', 'currency' => '2',
                'address1' => 'Oxford Street 1',
                'gov_id' => 'GOV-GB-001', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── Americas ──
            'US' => [
                'vat' => '12-3456789', 'id_number' => '12-3456789', 'tax_rate' => 0, 'tax_name' => 'Tax',
                'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001', 'currency' => '1',
                'address1' => 'Broadway 1',
                'gov_id' => '98-7654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'CA' => [
                'vat' => '123456789', 'id_number' => '123456789', 'tax_rate' => 5, 'tax_name' => 'GST',
                'city' => 'Toronto', 'state' => 'Ontario', 'postal_code' => 'M5H 2N2', 'currency' => '9',
                'address1' => 'Bay Street 1',
                'gov_id' => '987654321', 'individual_id' => '', 'individual_vat' => '',
            ],
            'MX' => [
                'vat' => 'XAXX010101000', 'id_number' => 'XAXX010101000', 'tax_rate' => 16, 'tax_name' => 'IVA',
                'city' => 'Mexico City', 'state' => 'CDMX', 'postal_code' => '06600', 'currency' => '28',
                'address1' => 'Paseo de la Reforma 1',
                'gov_id' => 'GOV120101AB3', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── Oceania ──
            'AU' => [
                'vat' => '12345678901', 'id_number' => 'ABN12345678901', 'tax_rate' => 10, 'tax_name' => 'GST',
                'city' => 'Sydney', 'state' => 'NSW', 'postal_code' => '2000', 'currency' => '12',
                'address1' => 'George Street 1',
                'gov_id' => 'ABN98765432100', 'individual_id' => '', 'individual_vat' => '',
            ],
            'NZ' => [
                'vat' => '123456789', 'id_number' => '123456789', 'tax_rate' => 15, 'tax_name' => 'GST',
                'city' => 'Auckland', 'state' => 'Auckland', 'postal_code' => '1010', 'currency' => '54',
                'address1' => 'Queen Street 1',
                'gov_id' => '987654321', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── Asia ──
            'IN' => [
                'vat' => '22AAAAA0000A1Z5', 'id_number' => '22AAAAA0000A1Z5', 'tax_rate' => 18, 'tax_name' => 'GST',
                'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400001', 'currency' => '11',
                'address1' => 'Marine Drive 1',
                'gov_id' => '27AABCU9603R1ZP', 'individual_id' => '', 'individual_vat' => '',
            ],
            'JP' => [
                'vat' => 'T1234567890123', 'id_number' => 'T1234567890123', 'tax_rate' => 10, 'tax_name' => 'CT',
                'city' => 'Tokyo', 'state' => 'Tokyo', 'postal_code' => '100-0001', 'currency' => '45',
                'address1' => 'Chiyoda 1',
                'gov_id' => 'T9876543210987', 'individual_id' => '', 'individual_vat' => '',
            ],
            'MY' => [
                'vat' => 'MY123456789012', 'id_number' => 'C12345678', 'tax_rate' => 8, 'tax_name' => 'SST',
                'city' => 'Kuala Lumpur', 'state' => 'WP Kuala Lumpur', 'postal_code' => '50000', 'currency' => '51',
                'address1' => 'Jalan Bukit Bintang 1',
                'gov_id' => 'GOV-MY-001', 'individual_id' => '', 'individual_vat' => '',
            ],
            'SG' => [
                'vat' => '202912345K', 'id_number' => '202912345K', 'tax_rate' => 9, 'tax_name' => 'GST',
                'city' => 'Singapore', 'state' => 'Singapore', 'postal_code' => '018960', 'currency' => '38',
                'address1' => 'Raffles Place 1',
                'gov_id' => 'T08GA0028A', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── GLN-only (no country-specific VAT, uses GLN as identifier) ──
            'XX' => [
                'vat' => '', 'id_number' => '5070004489700', 'tax_rate' => 19, 'tax_name' => 'VAT',
                'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10115', 'currency' => '3',
                'address1' => 'Unter den Linden 1',
                'gov_id' => 'GOV-GLN-001', 'individual_id' => '', 'individual_vat' => '',
            ],

            // ── Middle East ──
            'SA' => [
                'vat' => '3001234567890', 'id_number' => '3001234567890', 'tax_rate' => 15, 'tax_name' => 'VAT',
                'city' => 'Riyadh', 'state' => 'Riyadh', 'postal_code' => '11564', 'currency' => '44',
                'address1' => 'King Fahd Road 1',
                'gov_id' => 'GOV-SA-001', 'individual_id' => '', 'individual_vat' => '',
            ],
        ];
    }

    /**
     * EU country codes for region matching.
     */
    private array $eu_countries = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Storecove legal entity IDs per country for Peppol-connected companies.
     * When present, company.legal_entity_id is set and e_invoice_type = 'PEPPOL'.
     */
    private array $legal_entity_ids = [
        'AD' => 0, 
        'AL' => 0, 
        'AT' => 293801, // ATU92335648
        'AU' => 0, 
        'BA' => 0, 
        'BE' => 580406, //BE1000000417 - 1000000417
        'BG' => 0,
        'CA' => 0, 
        'CH' => 291394, //CHE923356489MWST
        'CY' => 0, 
        'CZ' => 0, 
        'DE' => 295616, // DE973356489
        //'DE' => 307482, //DE:STNR1234567890
        'DK' => 0, 
        'EE' => 0,
        'ES' => 0, 
        'FI' => 0, 
        'FR' => 293338, // FR82345678911
        'GB' => 0, 
        'GR' => 0, 
        'HR' => 0, 
        'HU' => 0,
        'IE' => 0, 
        'IN' => 0, 
        'IS' => 0, 
        'IT' => 291391, //IT92443356489 
        'JP' => 0, 
        'LI' => 0, 
        'LT' => 0,
        'LU' => 0, 
        'LV' => 0, 
        'MC' => 0, 
        'ME' => 0, 
        'MK' => 0, 
        'MT' => 0, 
        'MX' => 0,
        'MY' => 0, 
        'NL' => 0, 
        'NO' => 0, 
        'NZ' => 0, 
        'PL' => 0, 
        'PT' => 0, 
        'RO' => 294639, //RO010105019
        'RS' => 0, 
        'SA' => 0, 
        'SE' => 0, 
        'SG' => 637339, // UEN202912345K
        'SI' => 0, 
        'SK' => 0, 
        'SM' => 0,
        'TR' => 0, 
        'US' => 0, 
        'VA' => 0,
        'XX' => 634328, // GLN 5070004489700
    ];

    /**
     * Region groupings for picking "same region" cross-border partners.
     */
    private array $region_map = [
        'EU' => ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'],
        'BALKANS' => ['AL', 'BA', 'ME', 'MK', 'RS'],
        'EFTA' => ['CH', 'IS', 'LI', 'NO'],
        'MICROSTATES' => ['AD', 'MC', 'SM', 'VA'],
        'UK' => ['GB'],
        'AMERICAS' => ['US', 'CA', 'MX'],
        'OCEANIA' => ['AU', 'NZ'],
        'ASIA' => ['SG', 'MY', 'JP', 'IN'],
        'MIDDLE_EAST' => ['SA'],
        'TURKEY' => ['TR'],
    ];

    public function handle(): int
    {
        $countryCode = strtoupper($this->argument('country'));
        $defaults = $this->countryDefaults();

        if (!isset($defaults[$countryCode])) {
            $this->error("Unsupported country code: {$countryCode}");
            $this->info('Supported countries: ' . implode(', ', array_keys($defaults)));
            return self::FAILURE;
        }

        // XX is a GLN-only entity — uses DE as the base country
        $isGln = $countryCode === 'XX';
        $resolvedCountryCode = $isGln ? 'DE' : $countryCode;

        $country = Country::where('iso_3166_2', $resolvedCountryCode)->first();

        if (!$country) {
            $this->error("Country not found in database for code: {$resolvedCountryCode}");
            return self::FAILURE;
        }

        $cd = $defaults[$countryCode];

        $label = $isGln ? 'XX / GLN-only (based in DE)' : "{$countryCode} ({$country->full_name})";
        $this->info("Creating Peppol test data for {$label}...");

        // ── Look up existing dev user & account ──
        $user = User::whereEmail('small@example.com')->first();

        if (!$user) {
            $this->error("User 'small@example.com' not found. Run ninja:create-test-data first.");
            return self::FAILURE;
        }

        $account = $user->account;

        // ── Company ──
        $settings = CompanySettings::defaults();
        $settings->vat_number = $cd['vat'];
        $settings->id_number = $cd['id_number'];
        $settings->classification = 'business';
        $settings->country_id = (string) $country->id;
        $settings->email = "peppol-company-{$countryCode}@example.com";
        $settings->currency_id = $cd['currency'];
        $settings->e_invoice_type = 'PEPPOL';
        $settings->address1 = $cd['address1'];
        $settings->city = $cd['city'];
        $settings->state = $cd['state'];
        $settings->postal_code = $cd['postal_code'];
        $settings->name = "Peppol Test Company ({$countryCode})";

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = $resolvedCountryCode;
        $tax_data->acts_as_sender = true;
        $tax_data->acts_as_receiver = true;

        if (in_array($resolvedCountryCode, $this->eu_countries)) {
            $tax_data->regions->EU->has_sales_above_threshold = false;
            $tax_data->regions->EU->tax_all_subregions = true;
        }

        // ── E-invoice stub with PaymentMeans ──
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = 'DEUTDEMMXXX';

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $companyData = [
            'account_id' => $account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
            'e_invoice' => $stub,
        ];

        $legalEntityId = $this->legal_entity_ids[$countryCode] ?? 0;

        if ($legalEntityId) {
            $companyData['legal_entity_id'] = $legalEntityId;
            $settings->e_invoice_type = 'PEPPOL';
        }

        $company = Company::factory()->create($companyData);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'peppol test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $this->info("  Company created: {$settings->name}");

        // ── Domestic Clients (use resolved country for XX) ──
        $domesticDefaults = $isGln ? $defaults['DE'] : $cd;
        $this->createClient($company, $user, $domesticDefaults, $country, 'business', "Domestic Business Client ({$resolvedCountryCode})");
        $this->createClient($company, $user, $domesticDefaults, $country, 'government', "Domestic Government Client ({$resolvedCountryCode})");
        $this->createClient($company, $user, $domesticDefaults, $country, 'individual', "Domestic Individual Client ({$resolvedCountryCode})");

        // ── Same-region cross-border client ──
        $sameRegionCode = $this->pickSameRegionCountry($resolvedCountryCode);
        if ($sameRegionCode) {
            $srCountry = Country::where('iso_3166_2', $sameRegionCode)->first();
            $srDefaults = $defaults[$sameRegionCode];
            $this->createClient($company, $user, $srDefaults, $srCountry, 'business', "Same-Region Business Client ({$sameRegionCode})");
            $this->info("  Same-region cross-border client: {$sameRegionCode}");
        } else {
            $this->warn("  No same-region cross-border partner available for {$resolvedCountryCode}");
        }

        // ── Different-region cross-border client ──
        $diffRegionCode = $this->pickDifferentRegionCountry($resolvedCountryCode);
        $drCountry = Country::where('iso_3166_2', $diffRegionCode)->first();
        $drDefaults = $defaults[$diffRegionCode];
        $this->createClient($company, $user, $drDefaults, $drCountry, 'business', "Cross-Region Business Client ({$diffRegionCode})");
        $this->info("  Cross-region client: {$diffRegionCode}");

        $this->newLine();
        $this->info('Peppol test data created successfully.');
        $this->table(
            ['Entity', 'Detail'],
            [
                ['Account', "{$account->id} (existing, via small@example.com)"],
                ['Company', $isGln ? "{$settings->name} (GLN: {$cd['id_number']})" : "{$settings->name} (VAT: {$cd['vat']})"],
                ['User', $user->email],
                ['API Token', $company_token->token],
                ['Domestic clients', '3 (business, government, individual)'],
                ['Same-region client', $sameRegionCode ?: 'N/A'],
                ['Cross-region client', $diffRegionCode],
            ]
        );

        return self::SUCCESS;
    }

    private function createClient(Company $company, User $user, array $cd, Country $country, string $classification, string $name): Client
    {
        Client::unguard();

        $clientData = [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => $name,
            'classification' => $classification,
            'country_id' => $country->id,
            'address1' => $cd['address1'],
            'city' => $cd['city'],
            'state' => $cd['state'],
            'postal_code' => $cd['postal_code'],
            'settings' => ClientSettings::defaults(),
            'client_hash' => Str::random(40),
            'routing_id' => $cd['routing_id'] ?? '',
            'is_tax_exempt' => false,
        ];

        match ($classification) {
            'business' => $clientData = array_merge($clientData, [
                'vat_number' => $cd['vat'],
                'id_number' => $cd['id_number'],
                'has_valid_vat_number' => true,
            ]),
            'government' => $clientData = array_merge($clientData, [
                'vat_number' => $cd['vat'],
                'id_number' => $cd['gov_id'] ?? $cd['id_number'],
                'has_valid_vat_number' => true,
            ]),
            'individual' => $clientData = array_merge($clientData, [
                'vat_number' => $cd['individual_vat'] ?? '',
                'id_number' => $cd['individual_id'] ?? '',
                'has_valid_vat_number' => false,
            ]),
        };

        $client = Client::create($clientData);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'first_name' => ucfirst($classification),
            'last_name' => "Contact ({$country->iso_3166_2})",
            'email' => "peppol-{$classification}-{$country->iso_3166_2}@example.com",
        ]);

        $this->info("  Client created: {$name} [{$classification}]");

        return $client;
    }

    private function getRegion(string $countryCode): ?string
    {
        foreach ($this->region_map as $region => $countries) {
            if (in_array($countryCode, $countries)) {
                return $region;
            }
        }

        return null;
    }

    private function pickSameRegionCountry(string $countryCode): ?string
    {
        $region = $this->getRegion($countryCode);

        if (!$region) {
            return null;
        }

        $supported = array_keys($this->countryDefaults());
        $candidates = array_filter(
            $this->region_map[$region],
            fn (string $c) => $c !== $countryCode && in_array($c, $supported)
        );

        if (empty($candidates)) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }

    private function pickDifferentRegionCountry(string $countryCode): string
    {
        $region = $this->getRegion($countryCode);
        $supported = array_keys($this->countryDefaults());

        $candidates = array_filter($supported, function (string $c) use ($countryCode, $region) {
            $otherRegion = $this->getRegion($c);
            return $c !== $countryCode && $otherRegion !== $region;
        });

        if (empty($candidates)) {
            // Fallback: just pick any other supported country
            $candidates = array_filter($supported, fn (string $c) => $c !== $countryCode);
        }

        return $candidates[array_rand($candidates)];
    }
}
