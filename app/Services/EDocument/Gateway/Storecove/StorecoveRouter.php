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
     * Countries on the Peppol e-delivery network.
     * Curated subset of $routing_rules — excludes countries
     * routed via other networks (SDI, FacturX, etc.).
     */
    private static array $peppol_network = [
        'AD', 'AT', 'BE', 'DK', 'EE', 'FI', 'DE', 'IS',
        'LT', 'LU', 'NL', 'NO', 'PL', 'SE', 'IE',
        'FR', 'GR', 'PT', 'RO', 'SI', 'ES', 'GB',
    ];

    public static function isPeppolCountry(string $countryCode): bool
    {
        return in_array($countryCode, self::$peppol_network, true);
    }

    /** @return string[] */
    public static function peppolCountries(): array
    {
        return self::$peppol_network;
    }

    public function hasRoutingRules(string $countryCode): bool
    {
        return isset($this->routing_rules[$countryCode]);
    }

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
        "CA" => ["B","CA:CBN","CA:CBN","CA:CBN"],
        "MX" => ["B","MX:RFC","MX:RFC","MX:RFC"],
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
        "LU" => ["B+G","LU:VAT","LU:VAT","LU:VAT"],
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
        'SG:GST'   => '/^[A-Z0-9]{2}-\d{7}-[A-Z0-9]$/i',
        'SA:TIN'   => '/^\d{10,15}$/',
        'MY:TIN'   => '/^[A-Z0-9]{10,14}$/i',

        // ID number patterns (identifier1)
        'SE:ORGNR' => '/^\d{10}$/',
        'NO:ORG'   => '/^\d{9}$/',
        'BE:EN'    => '/^(BE)?[01]\d{9}$/i',
        'DK:DIGST' => '/^(DK)?\d{8}$/i',
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
        'SG:UEN'   => '/^[A-Z0-9]{9,16}$/i',
        'AT:GOV'   => '/^.{2,}$/',
        'DE:LWID'  => '/^.{2,}$/',
        'IT:CUUO'  => '/^[A-Z0-9]{6,7}$/i',
    ];

    /**
     * Human-readable format examples for identifier schemes.
     * Used in validation error messages to guide users.
     */
    private array $identifier_format_examples = [
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
        'FI:OVT'   => '123456789012',
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
    ];

    private $invoice;

    public function __construct() {}

    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * Routing rules column indices.
     *
     * Each routing rule is an array: [classification, identifier, tax, routing]
     * These constants name the columns for readability.
     */
    private const COL_IDENTIFIER = 1;
    private const COL_TAX        = 2;
    private const COL_ROUTING    = 3;

    /**
     * Map a classification label to the single-char code used in routing rules.
     */
    private function classificationCode(?string $classification): string
    {
        return match ($classification ?? 'business') {
            'government' => 'G',
            'individual' => 'C',
            default      => 'B',
        };
    }

    /**
     * Generic resolver: extract a column value from the routing rules
     * for a given country and classification.
     *
     * Checks the CountryFactory handler first (override callback, then
     * handler-provided rules), falling back to the built-in routing_rules.
     *
     * @param  string  $country         ISO 3166-2 country code
     * @param  string  $code            Classification code (B/G/C)
     * @param  int     $column          Column index to extract (use COL_* constants)
     * @param  ?string $overrideMethod  CountryHandler method to call for special-case overrides
     * @param  ?string $classification  Original classification label (passed to override)
     * @return string
     */
    private function resolveRuleColumn(string $country, string $code, int $column, ?string $overrideMethod = null, ?string $classification = null): string
    {
        if (CountryFactory::has($country)) {
            $handler = CountryFactory::make($country);

            if ($overrideMethod) {
                $override = $handler->$overrideMethod($classification, $this->invoice);
                if ($override !== null) {
                    return $override;
                }
            }

            $rules = $handler->getRoutingRules();
            if ($rules !== null) {
                return $this->extractFromRules($rules, $code, $column);
            }
        }

        $rules = $this->routing_rules[$country] ?? [false, false, false, false];

        return $this->extractFromRules($rules, $code, $column);
    }

    /**
     * Extract a column value from a single or multi-row rules array.
     *
     * @param  array  $rules  Single rule or array of rules
     * @param  string $code   Classification code to match (B/G/C)
     * @param  int    $column Column index to extract
     * @return string         The resolved value, or empty string if falsy
     */
    private function extractFromRules(array $rules, string $code, int $column): string
    {
        // Single-array country (e.g. ["B+G", "NO:ORG", "NO:VAT", "NO:ORG"])
        if (!is_array($rules[0])) {
            return $rules[$column] ?: '';
        }

        // Multi-array — find matching classification
        foreach ($rules as $rule) {
            if (stripos($rule[0], $code) !== false) {
                return $rule[$column] ?: '';
            }
        }

        return $rules[0][$column] ?: '';
    }

    /**
     * Resolve the routing identifier (rule column 3) for delivery.
     *
     * For most countries this is a scheme label like "SE:ORGNR".
     * For fixed-endpoint countries (e.g. SG Government) it may be a
     * composite "icd:endpointId" like "0195:SGUENT08GA0028A".
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveRouting(string $country, ?string $classification = 'business'): string
    {
        return $this->resolveRuleColumn(
            $country,
            $this->classificationCode($classification),
            self::COL_ROUTING,
            'resolveRoutingOverride',
            $classification,
        );
    }

    /**
     * Resolve the tax scheme (rule column 2) for a country/classification.
     *
     * Returns empty string when no tax scheme applies (e.g. government
     * entities that route via a central gateway rather than a tax identifier).
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveTaxScheme(string $country, ?string $classification = "business"): string
    {
        return $this->resolveRuleColumn(
            $country,
            $this->classificationCode($classification),
            self::COL_TAX,
            'resolveTaxSchemeOverride',
            $classification,
        );
    }

    /**
     * Resolve the identifier scheme (rule column 1) for a country/classification.
     *
     * This is the primary identifier type (e.g. SG:UEN, SE:ORGNR) as opposed
     * to the tax-specific scheme in column 2. Used as a fallback when the tax
     * scheme is empty (e.g. SG Government).
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveIdentifierScheme(string $country, ?string $classification = "business"): string
    {
        return $this->resolveRuleColumn(
            $country,
            $this->classificationCode($classification),
            self::COL_IDENTIFIER,
        );
    }

    /**
     * Checks whether a classification (business/government/individual) is routable
     * on the Peppol network for a given country.
     *
     * @param  string $country ISO 3166-2 country code
     * @param  string $classification business|government|individual
     * @return bool
     */
    public function isClassificationRoutable(string $country, string $classification): bool
    {
        $rules = $this->routing_rules[$country] ?? null;

        if (!$rules) {
            return false;
        }

        // 'other' bypasses e-invoicing altogether
        $code = $classification === 'other'
            ? 'O'
            : $this->classificationCode($classification);

        // Single-array country (e.g. ["B+G", ...])
        if (!is_array($rules[0])) {
            return stripos($rules[0], $code) !== false;
        }

        // Multi-array — check if any rule matches this classification
        foreach ($rules as $r) {
            if (stripos($r[0], $code) !== false) {
                return true;
            }
        }

        return false;
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

        $code = $this->classificationCode($classification);
        $rule = $this->findMatchingRule($rules, $code);

        $required = [];

        // Column 2 (tax_identifier) → vat_number
        if (!empty($rule[self::COL_TAX])) {
            $required['vat_number'] = $rule[self::COL_TAX];
        }

        // Column 1 (identifier) → id_number
        if (!empty($rule[self::COL_IDENTIFIER])) {
            $required['id_number'] = $rule[self::COL_IDENTIFIER];
        }

        // IT B2B/B2G requires routing_id (Codice Destinatario)
        if ($country === 'IT' && in_array($classification, ['business', 'government'])) {
            $required['routing_id'] = 'IT:CUUO';
        }

        return $required;
    }

    /**
     * Find the matching rule row for a classification code.
     *
     * @param  array  $rules  Single rule or array of rules
     * @param  string $code   Classification code (B/G/C)
     * @return array           The matched rule row
     */
    private function findMatchingRule(array $rules, string $code): array
    {
        // Single-array country
        if (!is_array($rules[0])) {
            return $rules;
        }

        // Multi-array — find matching classification
        foreach ($rules as $rule) {
            if (stripos($rule[0], $code) !== false) {
                return $rule;
            }
        }

        return $rules[0];
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

        if (!preg_match($this->identifier_regex[$scheme], $cleanValue)) {
            return false;
        }

        // Checkdigit validation (null = no algorithm for this scheme, treat as pass)
        $checkdigitResult = $this->checkdigit($scheme, $cleanValue);

        return $checkdigitResult !== false;
    }

    /**
     * Strict format check — preserves dashes/hyphens in the value.
     * Used at send-time to verify the value matches the exact format
     * expected by the delivery network (e.g. Storecove).
     */
    public function matchesSchemeFormat(string $scheme, string $value): bool
    {
        if (!isset($this->identifier_regex[$scheme])) {
            return strlen($value) >= 2;
        }

        return (bool) preg_match($this->identifier_regex[$scheme], $value);
    }

    /**
     * Validate the checkdigit of an identifier value for a given scheme.
     *
     * Can be called publicly to distinguish format errors from checkdigit errors.
     *
     * @param  string $scheme The scheme label (e.g. "BE:EN", "BE:VAT")
     * @param  string $value  The identifier value to validate
     * @return ?bool  true = valid, false = invalid checkdigit, null = no algorithm for this scheme
     */
    public function validateIdentifierCheckdigit(string $scheme, string $value): ?bool
    {
        $cleanValue = preg_replace("/[\s.\-]/", "", $value);

        return $this->checkdigit($scheme, $cleanValue);
    }

    /**
     * Internal checkdigit dispatch (operates on already-cleaned value).
     */
    private function checkdigit(string $scheme, string $cleanValue): ?bool
    {
        return match ($scheme) {
            'BE:EN' => $this->mod97Check($this->stripCountryPrefix($cleanValue, 'BE')),
            'BE:VAT' => $this->mod97Check($this->stripCountryPrefix($cleanValue, 'BE')),
            default => null,
        };
    }

    /**
     * Belgian mod-97 checkdigit: 97 - (first_8_digits % 97) == last_2_digits.
     *
     * @param  string $digits 10-digit number (without country prefix)
     */
    private function mod97Check(string $digits): bool
    {
        if (strlen($digits) !== 10 || !ctype_digit($digits)) {
            return false;
        }

        $body = (int) substr($digits, 0, 8);
        $check = (int) substr($digits, 8, 2);

        return (97 - ($body % 97)) === $check;
    }

    /**
     * Strip an optional country prefix from an identifier value.
     */
    private function stripCountryPrefix(string $value, string $prefix): string
    {
        if (stripos($value, $prefix) === 0) {
            return substr($value, strlen($prefix));
        }

        return $value;
    }

    /**
     * Get a human-readable format example for an identifier scheme.
     *
     * @param  string $scheme The scheme label (e.g. "SE:VAT", "FR:SIRET")
     * @return ?string Example format string, or null if none defined
     */
    public function getFormatExample(string $scheme): ?string
    {
        // Handle composite scheme labels like "FR:SIRENE or FR:SIRET"
        if (stripos($scheme, ' or ') !== false) {
            $schemes = array_map('trim', explode(' or ', $scheme));
            $examples = array_filter(array_map(fn ($s) => $this->getFormatExample($s), $schemes));
            return count($examples) > 0 ? implode(' or ', $examples) : null;
        }

        return $this->identifier_format_examples[$scheme] ?? null;
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
            'DE:STNR'    => '9930',  // German tax number (Steuernummer) for individuals
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

    /**
     * Returns a static delivery map for all supported countries.
     *
     * Each entry contains routability by classification and the required
     * client identifiers, so the UI can determine sendability without
     * calling the validation endpoint.
     *
     * @return array<string, array{
     *   classifications: array<string, bool>,
     *   required_fields: array<string, array<string, string>>
     * }>
     */
    public function getDeliveryMap(): array
    {
        $map = [];

        foreach ($this->routing_rules as $country => $rules) {
            $entry = [
                'classifications' => [
                    'business' => $this->isClassificationRoutable($country, 'business'),
                    'government' => $this->isClassificationRoutable($country, 'government'),
                    'individual' => $this->isClassificationRoutable($country, 'individual'),
                ],
                'required_fields' => [
                    'business' => $this->resolveRequiredClientFields($country, 'business'),
                    'government' => $this->resolveRequiredClientFields($country, 'government'),
                    'individual' => $this->resolveRequiredClientFields($country, 'individual'),
                ],
            ];

            $map[$country] = $entry;
        }

        return $map;
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
   
}
