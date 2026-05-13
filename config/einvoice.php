<?php

/**
 * E-Invoice routing configuration for Storecove/Peppol integration.
 *
 * This file contains static routing data previously embedded in StorecoveRouter.php.
 * Each section is keyed by its purpose:
 *
 *  - routing_rules: Country → [classification, identifier, tax_scheme, routing_scheme]
 *  - identifier_regex: Scheme → regex pattern for format validation
 *  - identifier_format_examples: Scheme → human-readable example for error messages
 *  - peppol_network: Countries on the Peppol e-delivery network
 *  - iso6523_map: Scheme label → ISO 6523 / EAS numeric code
 */

return [

    'peppol_network' => [
        'AD', 'AT', 'BE', 'DK', 'EE', 'FI', 'DE', 'IS',
        'LT', 'LU', 'NL', 'NO', 'PL', 'PT', 'SE', 'IE',
        'FR', 'GR', 'RO', 'SG', 'SI', 'ES', 'GB', 'IT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing Rules Matrix
    |--------------------------------------------------------------------------
    |
    | Each entry: [classification, identifier_scheme, tax_scheme, routing_scheme]
    |
    | Classification codes: B = business, G = government, C = consumer/individual
    | "B+G" means both business and government use the same rule.
    |
    | Multi-row countries (AT, DE, FR, IT, NL, SG) have an array of arrays,
    | one per classification.
    |
    */

    'routing_rules' => [
        "US" => [
            ["B", "DUNS, GLN, LEI", "US:EIN", "DUNS, GLN, LEI"],
        ],
        "CA" => ["B", "CA:CBN", "CA:CBN", "CA:CBN"],
        "MX" => ["B", "MX:RFC", "MX:RFC", "MX:RFC"],
        "AU" => ["B+G", "AU:ABN", "AU:ABN", "AU:ABN"],
        "NZ" => ["B+G", "GLN", "NZ:GST", "GLN"],
        "CH" => ["B+G", "CH:UIDB", "CH:VAT", "CH:UIDB"],
        "IS" => ["B+G", "IS:KTNR", "IS:VAT", "IS:KTNR"],
        "LI" => ["B+G", "", "LI:VAT", "LI:VAT"],
        "NO" => ["B+G", "NO:ORG", "NO:VAT", "NO:ORG"],
        "AD" => ["B+G", "", "AD:VAT", "AD:VAT"],
        "AL" => ["B+G", "", "AL:VAT", "AL:VAT"],
        "AT" => [
            ["G", "AT:GOV", false, "9915:b"],
            ["B", "", "AT:VAT", "AT:VAT"],
        ],
        "BA" => ["B+G", "", "BA:VAT", "BA:VAT"],
        "BE" => ["B+G", "BE:EN", "BE:VAT", "BE:EN"],
        "BG" => ["B+G", "", "BG:VAT", "BG:VAT"],
        "CY" => ["B+G", "", "CY:VAT", "CY:VAT"],
        "CZ" => ["B+G", "", "CZ:VAT", "CZ:VAT"],
        "DE" => [
            ["G", "DE:LWID", false, "DE:LWID"],
            ["B", "", "DE:VAT", "DE:VAT"],
        ],
        "DK" => ["B+G", "DK:DIGST", "DK:ERST", "DK:DIGST"],
        "EE" => ["B+G", "EE:CC", "EE:VAT", "EE:CC"],
        "ES" => ["B", "", "ES:VAT", "ES:VAT"],
        "FI" => ["B+G", "FI:OVT", "FI:VAT", "FI:OVT"],
        "FR" => [
            ["G", "FR:SIRET", false, "0009:11000201100044"],
            ["B", "FR:SIRENE or FR:SIRET", "FR:VAT", "FR:SIRENE or FR:SIRET"],
        ],
        "GR" => ["B+G", "", "GR:VAT", "GR:VAT"],
        "HR" => ["B+G", "", "HR:VAT", "HR:VAT"],
        "HU" => ["B+G", "", "HU:VAT", "HU:VAT"],
        "IE" => ["B+G", "", "IE:VAT", "IE:VAT"],
        "IT" => [
            ["G", "", "IT:IVA", "IT:CUUO"],
            ["B", "", "IT:IVA", "IT:CUUO"],
            ["C", "", "IT:CF", "Email"],
        ],
        "LT" => ["B+G", "LT:LEC", "LT:VAT", "LT:LEC"],
        "LU" => ["B+G", "LU:VAT", "LU:VAT", "LU:VAT"],
        "LV" => ["B+G", "", "LV:VAT", "LV:VAT"],
        "MC" => ["B+G", "", "MC:VAT", "MC:VAT"],
        "ME" => ["B+G", "", "ME:VAT", "ME:VAT"],
        "MK" => ["B+G", "", "MK:VAT", "MK:VAT"],
        "MT" => ["B+G", "", "MT:VAT", "MT:VAT"],
        "NL" => [
            ["B", "NL:KVK", "NL:VAT", "NL:VAT"],
            ["G", "NL:OINO", false, "NL:OINO"],
        ],
        "PL" => ["G+B", "", "PL:VAT", "PL:VAT"],
        "PT" => ["G+B", "", "PT:VAT", "PT:VAT"],
        "RO" => ["G+B", "", "RO:VAT", "RO:VAT"],
        "RS" => ["G+B", "", "RS:VAT", "RS:VAT"],
        "SE" => ["G+B", "SE:ORGNR", "SE:VAT", "SE:ORGNR"],
        "SI" => ["G+B", "", "SI:VAT", "SI:VAT"],
        "SK" => ["G+B", "", "SK:VAT", "SK:VAT"],
        "SM" => ["G+B", "", "SM:VAT", "SM:VAT"],
        "TR" => ["G+B", "", "TR:VAT", "TR:VAT"],
        "VA" => ["G+B", "", "VA:VAT", "VA:VAT"],
        "IN" => ["B", "", "IN:GSTIN", "Email"],
        "JP" => ["B", "JP:SST", "JP:IIN", "JP:SST"],
        "MY" => ["B", "MY:EIF", "MY:TIN", "MY:EIF"],
        "SG" => [
            ["G", "SG:UEN", false, "0195:SGUENT08GA0028A"],
            ["B", "SG:UEN", "SG:GST", "SG:UEN"],
        ],
        "GB" => ["B", "", "GB:VAT", "GB:VAT"],
        "SA" => ["B", "", "SA:TIN", "Email"],
        "Other" => ["B", "DUNS, GLN, LEI", false, "DUNS, GLN, LEI"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifier Format Regex Patterns
    |--------------------------------------------------------------------------
    */

    'identifier_regex' => [
        // VAT number patterns
        'AT:VAT'   => '/^(AT)?U\d{8}$/i',
        'BE:VAT'   => '/^(BE)?[01]\d{9}$/i',
        'BG:VAT'   => '/^(BG)?\d{9,10}$/i',
        'CY:VAT'   => '/^(CY)?\d{8}[A-Z]$/i',
        'CZ:VAT'   => '/^(CZ)?\d{8,10}$/i',
        'DE:VAT'   => '/^(DE)?\d{9}$/i',
        'DK:ERST'  => '/^(DK)?\d{8}$/i',
        'EE:VAT'   => '/^(EE)?\d{9}$/i',
        'ES:VAT'   => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
        'FI:VAT'   => '/^(FI)?\d{8}$/i',
        'FR:VAT'   => '/^(FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i',
        'GR:VAT'   => '/^(GR|EL)?\d{9}$/i',
        'HR:VAT'   => '/^(HR)?\d{11}$/i',
        'HU:VAT'   => '/^(HU)?\d{8}$/i',
        'IE:VAT'   => '/^(IE)?\d[A-Z0-9\+\*]\d{5}[A-Z]{1,2}$/i',
        'IT:IVA'   => '/^(IT)?\d{11}$/i',
        'IT:CF'    => '/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i',
        'LT:VAT'   => '/^(LT)?(\d{9}|\d{12})$/i',
        'LU:VAT'   => '/^(LU)?\d{8}$/i',
        'LV:VAT'   => '/^(LV)?\d{11}$/i',
        'MT:VAT'   => '/^(MT)?\d{8}$/i',
        'NL:VAT'   => '/^(NL)?\d{9}B\d{2}$/i',
        'PL:VAT'   => '/^(PL)?\d{10}$/i',
        'PT:VAT'   => '/^(PT)?\d{9}$/i',
        'RO:VAT'   => '/^(RO)?\d{2,10}$/i',
        'SE:VAT'   => '/^(SE)?\d{12}$/i',
        'SI:VAT'   => '/^(SI)?\d{8}$/i',
        'SK:VAT'   => '/^(SK)?\d{10}$/i',
        'AD:VAT'   => '/^(AD)?[A-Z]\d{6}[A-Z]$/i',
        'AL:VAT'   => '/^(AL)?[A-Z]\d{8}[A-Z]$/i',
        'BA:VAT'   => '/^(BA)?\d{12}$/i',
        'LI:VAT'   => '/^(LI)?\d{5}$/i',
        'MC:VAT'   => '/^(MC|FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i',
        'ME:VAT'   => '/^(ME)?\d{8}$/i',
        'MK:VAT'   => '/^(MK)?\d{13}$/i',
        'SM:VAT'   => '/^(SM)?\d{5}$/i',
        'TR:VAT'   => '/^(TR)?\d{10}$/i',
        'VA:VAT'   => '/^(VA)?\d{11}$/i',
        'RS:VAT'   => '/^(RS)?\d{9}$/i',
        'IS:VAT'   => '/^(IS)?\d{5,6}$/i',
        'NO:VAT'   => '/^(NO)?\d{9}(MVA)?$/i',
        'CH:VAT'   => '/^(CHE)?\d{9}(MWST|TVA|IVA)?$/i',
        'GB:VAT'   => '/^(GB)?\d{9}(\d{3})?$/i',
        'AU:ABN'   => '/^\d{11}$/',
        'NZ:GST'   => '/^\d{8,9}$/',
        'US:EIN'   => '/^\d{2}\-?\d{7}$/',
        'IN:GSTIN' => '/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z0-9][A-Z0-9]$/i',
        'JP:IIN'   => '/^T?\d{13}$/',
        'SG:GST'   => '/^[A-Z0-9]{2}-\d{7}-[A-Z0-9]$/i',
        'SA:TIN'   => '/^\d{10,15}$/',
        'MY:TIN'   => '/^[A-Z0-9]{10,14}$/i',

        // ID number patterns
        'SE:ORGNR' => '/^\d{10}$/',
        'NO:ORG'   => '/^\d{9}$/',
        'BE:EN'    => '/^(BE)?[01]\d{9}$/i',
        'DK:DIGST' => '/^(DK)?\d{8}$/i',
        'EE:CC'    => '/^\d{8}$/',
        'FI:OVT'   => '/^\d{12,13}[a-zA-Z0-9]{0,5}$/',
        'FR:SIRENE' => '/^\d{9}$/',
        'FR:SIRET' => '/^\d{14}$/',
        'NL:KVK'   => '/^\d{8}$/',
        'NL:OINO'  => '/^\d{20}$/',
        'LT:LEC'   => '/^\d{7,9}$/',
        'LU:MAT'   => '/^\d{11}$/',
        'CH:UIDB'  => '/^(CHE)?\d{9}$/i',
        'IS:KTNR'  => '/^\d{6,10}$/',
        'CA:CBN'   => '/^\d{9}$/',
        'MX:RFC'   => '/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/i',
        'JP:SST'   => '/^T?\d{13}$/',
        'MY:EIF'   => '/^[A-Z0-9]{10,14}$/i',
        'SG:UEN'   => '/^[A-Z0-9]{9,16}$/i',
        'AT:GOV'   => '/^.+$/',
        // Leitweg-ID: Grobadresse[0-12 digits]-Feinadresse[0-30 alphanum]-Prüfziffer[2 digits], total ≤ 45.
        'DE:LWID'  => '/(?=.{0,45}$)^[0-9]{0,12}(\-[0-9a-zA-Z]{0,30}(\-[0-9]{2}))$/',
        'IT:CUUO'  => '/^[A-Z0-9]{6,7}$/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Examples (for validation error messages)
    |--------------------------------------------------------------------------
    */

    'identifier_format_examples' => [
        // VAT number formats
        'AT:VAT'   => 'ATU12345678',
        'BE:VAT'   => 'BE0202239951',
        'BG:VAT'   => 'BG123456789',
        'CY:VAT'   => 'CY12345678A',
        'CZ:VAT'   => 'CZ12345678',
        'DE:VAT'   => 'DE123456789',
        'DK:ERST'  => 'DK12345678',
        'EE:VAT'   => 'EE123456789',
        'ES:VAT'   => 'ESA1234567B',
        'FI:VAT'   => 'FI12345678',
        'FR:VAT'   => 'FRXX123456789',
        'GR:VAT'   => 'EL123456789',
        'HR:VAT'   => 'HR12345678901',
        'HU:VAT'   => 'HU12345678',
        'IE:VAT'   => 'IE1A23456B',
        'IT:IVA'   => 'IT12345678901',
        'IT:CF'    => 'RSSMRA85M01H501Z',
        'LT:VAT'   => 'LT123456789',
        'LU:VAT'   => 'LU12345678',
        'LV:VAT'   => 'LV12345678901',
        'MT:VAT'   => 'MT12345678',
        'NL:VAT'   => 'NL123456789B01',
        'PL:VAT'   => 'PL1234567890',
        'PT:VAT'   => 'PT123456789',
        'RO:VAT'   => 'RO1234567890',
        'SE:VAT'   => 'SE123456789012',
        'SI:VAT'   => 'SI12345678',
        'SK:VAT'   => 'SK1234567890',
        'AD:VAT'   => 'ADA123456B',
        'AL:VAT'   => 'ALA12345678B',
        'BA:VAT'   => 'BA123456789012',
        'LI:VAT'   => 'LI12345',
        'MC:VAT'   => 'FRXX123456789',
        'ME:VAT'   => 'ME12345678',
        'MK:VAT'   => 'MK1234567890123',
        'SM:VAT'   => 'SM12345',
        'TR:VAT'   => 'TR1234567890',
        'VA:VAT'   => 'VA12345678901',
        'RS:VAT'   => 'RS123456789',
        'IS:VAT'   => 'IS12345',
        'NO:VAT'   => 'NO123456789MVA',
        'CH:VAT'   => 'CHE123456789MWST',
        'GB:VAT'   => 'GB123456789',
        'AU:ABN'   => '12345678901',
        'NZ:GST'   => '12345678',
        'US:EIN'   => '12-3456789',
        'IN:GSTIN' => '12ABCDE1234F1Z1',
        'JP:IIN'   => 'T1234567890123',
        'SG:GST'   => 'M2-1234567-X',
        'SA:TIN'   => '1234567890',
        'MY:TIN'   => 'C1234567890',

        // ID number formats
        'SE:ORGNR' => '1234567890',
        'NO:ORG'   => '123456789',
        'BE:EN'    => '0202239951',
        'DK:DIGST' => 'DK12345678',
        'EE:CC'    => '12345678',
        'FI:OVT'   => '003712345678',
        'FR:SIRENE' => '123456789',
        'FR:SIRET' => '12345678901234',
        'NL:KVK'   => '12345678',
        'NL:OINO'  => '12345678901234567890',
        'LT:LEC'   => '1234567',
        'LU:MAT'   => '12345678901',
        'CH:UIDB'  => 'CHE123456789',
        'IS:KTNR'  => '123456',
        'CA:CBN'   => '123456789',
        'MX:RFC'   => 'ABC1234567A1',
        'JP:SST'   => 'T1234567890123',
        'MY:EIF'   => 'C1234567890',
        'SG:UEN'   => '12345678A',
        'IT:CUUO'  => 'A1B2C3',
    ],

    /*
    |--------------------------------------------------------------------------
    | ISO 6523 / EAS Scheme Code Map
    |--------------------------------------------------------------------------
    |
    | Maps Storecove/PEPPOL scheme labels to ISO 6523 EAS numeric codes
    | for use in UBL document EndpointID and PartyIdentification schemeID.
    |
    */

    'iso6523_map' => [
        // ICD codes (ISO 6523 / PEPPOL EAS)
        'FR:SIRENE'  => '0002',
        'SE:ORGNR'   => '0007',
        'FR:SIRET'   => '0009',
        'FI:OVT'     => '0037',
        'DUNS'       => '0060',
        'GLN'        => '0088',
        'NL:KVK'     => '0106',
        'AU:ABN'     => '0151',
        'CH:UIDB'    => '0183',
        'DK:DIGST'   => '0184',
        'DK:ERST'    => '0198',
        'NL:OINO'    => '0190',
        'EE:CC'      => '0191',
        'NO:ORG'     => '0192',
        
        'SG:UEN'     => '0195',
        'IS:KTNR'    => '0196',
        'LEI'        => '0199',
        'LT:LEC'     => '0200',
        'IT:CUUO'    => '0201',
        'DE:LWID'    => '0204',
        'BE:EN'      => '0208',
        'IT:CF'      => '0210',
        'IT:IVA'     => '0211',
        'FI:ORG'     => '0212',
        'FI:VAT'     => '0213',
        'JP:IIN'     => '0221',
        'JP:SST'     => '0188',
        'MY:EIF'     => '0230',
        'AE:TIN'     => '0235',
        // EAS codes (OpenPEPPOL 9xxx range — VAT-based schemes)
        'HU:VAT'     => '9910',
        'AT:VAT'     => '9914',
        'AT:GOV'     => '9915',
        'ES:VAT'     => '9920',
        'AD:VAT'     => '9922',
        'AL:VAT'     => '9923',
        'BA:VAT'     => '9924',
        'BE:VAT'     => '9925',
        'BG:VAT'     => '9926',
        'CH:VAT'     => '9927',
        'CY:VAT'     => '9928',
        'CZ:VAT'     => '9929',
        'DE:VAT'     => '9930',
        'DE:STNR'    => '9930',
        'EE:VAT'     => '9931',
        'GB:VAT'     => '9932',
        'GR:VAT'     => '9933',
        'HR:VAT'     => '9934',
        'IE:VAT'     => '9935',
        'LI:VAT'     => '9936',
        'LT:VAT'     => '9937',
        'LU:VAT'     => '9938',
        'LV:VAT'     => '9939',
        'MC:VAT'     => '9940',
        'ME:VAT'     => '9941',
        'MK:VAT'     => '9942',
        'MT:VAT'     => '9943',
        'NL:VAT'     => '9944',
        // 'NO:VAT'     => '9909', deprecated from EAS
        'PL:VAT'     => '9945',
        'PT:VAT'     => '9946',
        'RO:VAT'     => '9947',
        'RS:VAT'     => '9948',
        'SI:VAT'     => '9949',
        'SK:VAT'     => '9950',
        'SM:VAT'     => '9951',
        'TR:VAT'     => '9952',
        'VA:VAT'     => '9953',
        'FR:VAT'     => '9957',
        'US:EIN'     => '9959',
    ],

];
