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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Standards\Peppol\CountryFactory;

class StorecoveRouter
{
    /**
     * Provides a country matrix for the correct scheme to send via
     * [ "iso_3166_2" =>  [<business_type>, <identifier1>, <tax_identifier>, <routing_identifier>]
     * @var array $routing_rules
     **/
    private array $routing_rules = [
        "US" => [
            ["B","DUNS, GLN, LEI","US:EIN","DUNS, GLN, LEI"],
            // ["B","DUNS, GLN, LEI","US:SSN","DUNS, GLN, LEI"],
        ],
        "CA" => ["B","CA:CBN",false,"CA:CBN"],
        "MX" => ["B","MX:RFC",false,"MX:RFC"],
        "AU" => ["B+G","AU:ABN","AU:ABN","AU:ABN"],
        "NZ" => ["B+G","GLN","NZ:GST","GLN"],
        "CH" => ["B+G","CH:UIDB","CH:VAT","CH:UIDB"],
        "IS" => ["B+G","IS:KTNR","IS:VAT","IS:KTNR"],
        "LI" => ["B+G","","LI:VAT","LI:VAT"],
        "NO" => ["B+G","NO:ORG","NO:VAT","NO:ORG"],
        "AD" => ["B+G","","AD:VAT","AD:VAT"],
        "AL" => ["B+G","","AL:VAT","AL:VAT"],
        "AT" => [
            ["G","AT:GOV",false,"9915:b"],
            ["B","","AT:VAT","AT:VAT"],
        ],
        "BA" => ["B+G","","BA:VAT","BA:VAT"],
        "BE" => ["B+G","BE:EN","BE:VAT","BE:EN"],
        "BG" => ["B+G","","BG:VAT","BG:VAT"],
        "CY" => ["B+G","","CY:VAT","CY:VAT"],
        "CZ" => ["B+G","","CZ:VAT","CZ:VAT"],
        "DE" => [
            ["G","DE:LWID",false,"DE:LWID"],
            ["B","","DE:VAT","DE:VAT"],
        ],
        "DK" => ["B+G","DK:DIGST","DK:ERST","DK:DIGST"],
        "EE" => ["B+G","EE:CC","EE:VAT","EE:CC"],
        "ES" => ["B","","ES:VAT","ES:VAT"],
        "FI" => ["B+G","FI:OVT","FI:VAT","FI:OVT"],
        "FR" => [
            ["G","FR:SIRET + customerAssignedAccountIdValue",false,"0009:11000201100044"],
            ["B","FR:SIRENE or FR:SIRET","FR:VAT","FR:SIRENE or FR:SIRET"],
        ],
        "GR" => ["B+G","","GR:VAT","GR:VAT"],
        "HR" => ["B+G","","HR:VAT","HR:VAT"],
        "HU" => ["B+G","","HU:VAT","HU:VAT"],
        "IE" => ["B+G","","IE:VAT","IE:VAT"],
        "IS" => ["B+G","IS:KTNR","IS:VAT","IS:KTNR"],
        "IT" => [
            ["G","","IT:IVA","IT:CUUO"], // (Peppol)
            ["B","","IT:IVA","IT:CUUO"], // (SDI)
            // ["B","","IT:CF","IT:CUUO"], // (SDI)
            ["C","","IT:CF","Email"],// (SDI)
            ["G","","IT:IVA","IT:CUUO"],// (SDI)
        ],
        "LT" => ["B+G","LT:LEC","LT:VAT","LT:LEC"],
        "LU" => ["B+G","LU:MAT","LU:VAT","LU:VAT"],
        "LV" => ["B+G","","LV:VAT","LV:VAT"],
        "MC" => ["B+G","","MC:VAT","MC:VAT"],
        "ME" => ["B+G","","ME:VAT","ME:VAT"],
        "MK" => ["B+G","","MK:VAT","MK:VAT"],
        "MT" => ["B+G","","MT:VAT","MT:VAT"],
        "NL" => [
            ["B","NL:KVK","NL:VAT","NL:VAT"],
            ["G","NL:OINO",false,"NL:OINO"],
        ],
        "PL" => ["G+B","","PL:VAT","PL:VAT"],
        "PT" => ["G+B","","PT:VAT","PT:VAT"],
        "RO" => ["G+B","","RO:VAT","RO:VAT"],
        "RS" => ["G+B","","RS:VAT","RS:VAT"],
        "SE" => ["G+B","SE:ORGNR","SE:VAT","SE:ORGNR"],
        "SI" => ["G+B","","SI:VAT","SI:VAT"],
        "SK" => ["G+B","","SK:VAT","SK:VAT"],
        "SM" => ["G+B","","SM:VAT","SM:VAT"],
        "TR" => ["G+B","","TR:VAT","TR:VAT"],
        "VA" => ["G+B","","VA:VAT","VA:VAT"],
        "IN" => ["B","","IN:GSTIN","Email"],
        "JP" => ["B","JP:SST","JP:IIN","JP:SST"],
        "MY" => ["B","MY:EIF","MY:TIN","MY:EIF"],
        "SG" => [
            ["G","SG:UEN",false,"0195:SGUENT08GA0028A"],
            ["B","SG:UEN","SG:GST","SG:UEN"],
        ],
        "GB" => ["B","","GB:VAT","GB:VAT"],
        "SA" => ["B","","SA:TIN","Email"],
        "Other" => ["B","DUNS, GLN, LEI",false,"DUNS, GLN, LEI"],
    ];

    /**
     * Format validation regex patterns for identifiers.
     * Keys match the scheme labels from routing_rules.
     * Patterns strip common prefixes/separators before matching.
     */
    private array $identifier_regex = [
        // VAT number patterns (tax_identifier)
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
        'SG:GST'   => '/^[A-Z0-9]{8,10}$/i',
        'SA:TIN'   => '/^\d{10,15}$/',
        'MY:TIN'   => '/^[A-Z0-9]{10,14}$/i',

        // ID number patterns (identifier1)
        'SE:ORGNR' => '/^\d{10}$/',
        'NO:ORG'   => '/^\d{9}$/',
        'BE:EN'    => '/^(BE)?\d{10}$/i',
        'DK:DIGST' => '/^\d{8,10}$/',
        'EE:CC'    => '/^\d{8}$/',
        'FI:OVT'   => '/^\d{12,13}$/',
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
        'SG:UEN'   => '/^[A-Z0-9]{9,10}$/i',
        'AT:GOV'   => '/^.{2,}$/',
        'DE:LWID'  => '/^.{2,}$/',
        'IT:CUUO'  => '/^[A-Z0-9]{6,7}$/i',
    ];

    private $invoice;

    public function __construct() {}

    /**
     * Return the routing code based on country and entity classification
     *
     * @param  string $country
     * @param  ?string $classification DE:STNR
     * @return string
     */
    public function resolveRouting(string $country, ?string $classification = 'business'): string
    {
        $code = 'B';

        match ($classification) {
            "business" => $code = "B",
            "government" => $code = "G",
            "individual" => $code = "C",
            default => $code = "B",
        };

        // Try country handler first
        if (CountryFactory::has($country)) {
            $handler = CountryFactory::make($country);

            // Check for special-case override
            $override = $handler->resolveRoutingOverride($classification, $this->invoice);
            if ($override !== null) {
                return $override;
            }

            // Check for handler-provided routing rules
            $rules = $handler->getRoutingRules();
            if ($rules !== null) {
                return $this->resolveFromRules($rules, $code);
            }
        }

        // Fall back to built-in routing_rules array
        $rules = $this->routing_rules[$country];

        return $this->resolveFromRules($rules, $code);
    }

    /**
     * Resolve routing identifier from a rules array.
     */
    private function resolveFromRules(array $rules, string $code): string
    {
        //Single array
        if (!is_array($rules[0])) {
            return $rules[3];
        }

        //Multi Array - iterate
        foreach ($rules as $rule) {
            if (stripos($rule[0], $code) !== false) {
                return $rule[3];
            }
        }

        return $rules[0][3];
    }

    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }
    /**
     * resolveTaxScheme
     *
     * @param  string $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveTaxScheme(string $country, ?string $classification = "business"): string
    {
        $code = "B";

        match ($classification) {
            "business" => $code = "B",
            "government" => $code = "G",
            "individual" => $code = "C",
            default => $code = "B",
        };

        // Try country handler first
        if (CountryFactory::has($country)) {
            $handler = CountryFactory::make($country);

            // Check for special-case override
            $override = $handler->resolveTaxSchemeOverride($classification, $this->invoice);
            if ($override !== null) {
                return $override;
            }

            // Check for handler-provided routing rules
            $rules = $handler->getRoutingRules();
            if ($rules !== null) {
                return $this->resolveTaxFromRules($rules, $code);
            }
        }

        // Fall back to built-in routing_rules array
        $rules = $this->routing_rules[$country] ?? [false, false, false, false];

        return $this->resolveTaxFromRules($rules, $code);
    }

    /**
     * Resolve tax scheme from a rules array.
     */
    private function resolveTaxFromRules(array $rules, string $code)
    {
        //single array
        if (!is_array($rules[0])) {
            return $rules[2];
        }

        foreach ($rules as $rule) {
            if (stripos($rule[0], $code) !== false) {
                return $rule[2];
            }
        }

        return $rules[0][2];
    }

    /**
     * Returns the required client fields for a given country/classification.
     *
     * Derives requirements from the routing_rules matrix:
     * - Column 1 (identifier1) non-empty → id_number required
     * - Column 2 (tax_identifier) non-empty → vat_number required
     * - Both can be required simultaneously (e.g. SE needs ORGNR + VAT)
     * - IT B2B/B2G additionally requires routing_id (IT:CUUO)
     *
     * @param  string $country ISO 3166-2 country code
     * @param  ?string $classification business|government|individual
     * @return array<string, string> Keys are client field names, values are scheme labels
     */
    public function resolveRequiredClientFields(string $country, ?string $classification = 'business'): array
    {
        $rules = $this->routing_rules[$country] ?? null;

        if (!$rules) {
            return [];
        }

        // Individuals route via email — no identifier requirements
        if ($classification === 'individual') {
            return [];
        }

        $code = match ($classification) {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };

        // Find the matching rule
        $rule = null;

        // Single-array country (applies to all classifications)
        if (is_array($rules) && !is_array($rules[0])) {
            $rule = $rules;
        } else {
            // Multi-array — find matching classification
            foreach ($rules as $r) {
                if (stripos($r[0], $code) !== false) {
                    $rule = $r;
                    break;
                }
            }
            // Fallback to first rule if no match
            if (!$rule) {
                $rule = $rules[0];
            }
        }

        $required = [];

        // Column 2: tax_identifier → vat_number
        if (!empty($rule[2])) {
            $required['vat_number'] = $rule[2];
        }

        // Column 1: identifier1 → id_number
        if (!empty($rule[1])) {
            $required['id_number'] = $rule[1];
        }

        // IT B2B/B2G requires routing_id (Codice Destinatario)
        if ($country === 'IT' && in_array($classification, ['business', 'government'])) {
            $required['routing_id'] = 'IT:CUUO';
        }

        return $required;
    }

    /**
     * Validate an identifier value against the expected format for a scheme.
     *
     * @param  string $scheme The scheme label (e.g. "SE:VAT", "FR:SIRET")
     * @param  string $value  The identifier value to validate
     * @return bool True if valid or no regex defined for scheme
     */
    public function validateIdentifierFormat(string $scheme, string $value): bool
    {
        // Handle composite scheme labels like "FR:SIRENE or FR:SIRET"
        if (stripos($scheme, ' or ') !== false) {
            $schemes = array_map('trim', explode(' or ', $scheme));
            foreach ($schemes as $s) {
                if ($this->validateIdentifierFormat($s, $value)) {
                    return true;
                }
            }
            return false;
        }

        // Handle "DUNS, GLN, LEI" style — no strict format validation
        if (stripos($scheme, ',') !== false) {
            return strlen($value) >= 2;
        }

        // Handle scheme + extra info like "FR:SIRET + customerAssignedAccountIdValue"
        if (stripos($scheme, ' + ') !== false) {
            return strlen(preg_replace("/[\s.\-]/", "", $value)) >= 2;
        }

        $cleanValue = preg_replace("/[\s.\-]/", "", $value);

        if (!isset($this->identifier_regex[$scheme])) {
            // No regex defined — just check presence
            return strlen($cleanValue) >= 2;
        }

        return (bool) preg_match($this->identifier_regex[$scheme], $cleanValue);
    }

    /**
     * resolveIso6523Scheme
     *
     * Maps a Storecove/PEPPOL friendly scheme name to its ISO 6523 / EAS numeric code
     * for use in UBL document EndpointID and PartyIdentification schemeID attributes.
     * Numeric-only inputs are returned as-is (already an ISO code).
     *
     * @param  string $scheme  e.g. 'GLN', 'DE:LWID', 'BE:EN', 'DE:VAT'
     * @return string          ISO 6523 EAS code, e.g. '0088', '0204', '0208', '9930'
     */
    public function resolveIso6523Scheme(string $scheme): string
    {
        // Already a numeric ISO code — pass through
        if (ctype_digit($scheme)) {
            return $scheme;
        }

        $map = [
            // ICD codes (ISO 6523 / PEPPOL EAS)
            'FR:SIRENE'  => '0002',  // French SIRENE (company registry)
            'SE:ORGNR'   => '0007',  // Swedish organisation number
            'FR:SIRET'   => '0009',  // French SIRET (establishment)
            'FI:OVT'     => '0037',  // Finnish OVT identifier
            'DUNS'       => '0060',  // DUNS number
            'GLN'        => '0088',  // GS1 Global Location Number
            'NL:KVK'     => '0106',  // Dutch Chamber of Commerce
            'AU:ABN'     => '0151',  // Australian Business Number
            'CH:UIDB'    => '0183',  // Swiss UID-B
            'DK:DIGST'   => '0184',  // Danish CVR / DIGST
            'NL:OINO'    => '0190',  // Dutch government OINO
            'EE:CC'      => '0191',  // Estonian company code
            'NO:ORG'     => '0192',  // Norwegian organisation number
            'SG:UEN'     => '0195',  // Singapore UEN
            'IS:KTNR'    => '0196',  // Icelandic legal entity
            'DK:ERST'    => '0198',  // Danish ERST
            'LT:LEC'     => '0200',  // Lithuanian legal entity code
            'IT:CUUO'    => '0201',  // Italian IPA code (public administration)
            'DE:LWID'    => '0204',  // German Leitweg-ID
            'BE:EN'      => '0208',  // Belgian enterprise number
            'IT:CF'      => '0210',  // Italian Codice Fiscale
            'IT:IVA'     => '0211',  // Italian Partita IVA
            'FI:ORG'     => '0212',  // Finnish organisation identifier
            'JP:IIN'     => '0221',  // Japanese invoicing institution number
            'JP:SST'     => '0221',
            'MY:EIF'     => '0230',  // Malaysian e-invoice framework

            // EAS codes (OpenPEPPOL 9xxx range — VAT-based schemes)
            'HU:VAT'     => '9910',
            'AT:VAT'     => '9914',  // Austrian VAT (Umsatzsteuer-ID)
            'AT:GOV'     => '9915',  // Austrian administrative (Verwaltungs-ID)
            'ES:VAT'     => '9920',  // Spanish tax authority scheme
            'AD:VAT'     => '9922',
            'AL:VAT'     => '9923',
            'BA:VAT'     => '9924',
            'BE:VAT'     => '9925',
            'BG:VAT'     => '9926',
            'CH:VAT'     => '9927',
            'CY:VAT'     => '9928',
            'CZ:VAT'     => '9929',
            'DE:VAT'     => '9930',
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
            'NO:VAT'     => '9909',  // deprecated in EAS but still in use
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
        ];

        return $map[$scheme] ?? $scheme;
    }

    public function resolveIdentifierTypeByValue(string $identifier): string
    {
        $parts = explode(":", $identifier);
        $country = $parts[0];

        /** When using HERMES, the country does not resolve, we cast back to BE here. */
        if ($country == 'LEI') {
            $country = 'BE';
            $identifier = 'BE:VAT';
        } elseif (in_array($country,['GLN','0087'])) { // handle GLN and 0087 prefix
            return 'routing_id';
        }

        $rules = $this->routing_rules[$country];

        if (is_array($rules) && !is_array($rules[0])) {

            if (stripos($identifier, $rules[2]) !== false) {
                return 'vat_number';
            } elseif (stripos($identifier, $rules[3]) !== false) {
                return 'id_number';
            }

        } else {
            foreach ($rules as $country_identifiers) {

                if (stripos($identifier, $country_identifiers[2]) !== false) {
                    return 'vat_number';
                } elseif (stripos($identifier, $country_identifiers[3]) !== false) {
                    return 'id_number';
                }
            }
        }

        return '';

    }
    /**
    * used as a proxy for
    * the schemeID of partyidentification
    * property - for Storecove only:
    *
    * Used in the format key:value
    *
    * ie. IT:IVA / DE:VAT
    *
    * Note there are multiple options for the following countries:
    *
    * US (EIN/SSN) employer identification number / social security number
    * IT (CF/IVA) Codice Fiscale (person/company identifier) / company vat number
    *
    * @var array
    * @deprecated
    */
    private array $schemeIdIdentifiers = [
        'US' => 'EIN',
        'US' => 'SSN',
        'NZ' => 'GST',
        'CH' => 'VAT', // VAT number = CHE - 999999999 - MWST|IVA|VAT
        'IS' => 'VAT',
        'LI' => 'VAT',
        'NO' => 'VAT',
        'AD' => 'VAT',
        'AL' => 'VAT',
        'AT' => 'VAT', //Tested - Routing GOV + Business
        'BA' => 'VAT',
        'BE' => 'VAT',
        'BG' => 'VAT',
        'AU' => 'ABN', //Australia
        'CA' => 'CBN', //Canada
        'MX' => 'RFC', //Mexico
        'NZ' => 'GST', //Nuuu zulund
        'GB' => 'VAT', //Great Britain
        'SA' => 'TIN', //South Africa
        'CY' => 'VAT',
        'CZ' => 'VAT',
        'DE' => 'VAT', //tested - Requires Payment Means to be defined.
        'DK' => 'ERST',
        'EE' => 'VAT',
        'ES' => 'VAT', //tested - B2G pending
        'FI' => 'VAT',
        'FR' => 'VAT', //tested - Need to ensure Siren/Siret routing
        'GR' => 'VAT',
        'HR' => 'VAT',
        'HU' => 'VAT',
        'IE' => 'VAT',
        'IT' => 'IVA', //tested - Requires a Customer Party Identification (VAT number) - 'IT senders must first be provisioned in the partner system.' Cannot test currently
        'IT' => 'CF', //tested - Requires a Customer Party Identification (VAT number) - 'IT senders must first be provisioned in the partner system.' Cannot test currently
        'LT' => 'VAT',
        'LU' => 'VAT',
        'LV' => 'VAT',
        'MC' => 'VAT',
        'ME' => 'VAT',
        'MK' => 'VAT',
        'MT' => 'VAT',
        'NL' => 'VAT',
        'PL' => 'VAT',
        'PT' => 'VAT',
        'RO' => 'VAT',
        'RS' => 'VAT',
        'SE' => 'VAT',
        'SI' => 'VAT',
        'SK' => 'VAT',
        'SM' => 'VAT',
        'TR' => 'VAT',
        'VA' => 'VAT',
        'IN' => 'GSTIN',
        'JP' => 'IIN',
        'MY' => 'TIN',
        'SG' => 'GST',
        'GB' => 'VAT',
        'SA' => 'TIN',
    ];

}
