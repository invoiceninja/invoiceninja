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
    public static function isPeppolCountry(string $countryCode): bool
    {
        return in_array($countryCode, config('einvoice.peppol_network'), true);
    }

    /** @return string[] */
    public static function peppolCountries(): array
    {
        return config('einvoice.peppol_network');
    }

    public function hasRoutingRules(string $countryCode): bool
    {
        return isset($this->routing_rules[$countryCode]);
    }

    /** Routing rules loaded from config/einvoice.php */
    private array $routing_rules;

    /** Format validation regex patterns loaded from config/einvoice.php */
    private array $identifier_regex;

    /** Human-readable format examples loaded from config/einvoice.php */
    private array $identifier_format_examples;

    private $invoice;

    public function __construct()
    {
        $this->routing_rules = config('einvoice.routing_rules') ?? [];
        $this->identifier_regex = config('einvoice.identifier_regex') ?? [];
        $this->identifier_format_examples = config('einvoice.identifier_format_examples') ?? [];
    }

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
    private function resolveRuleColumn(string $country, string $code, int $column): string
    {
        if (CountryFactory::has($country)) {
            $handler = CountryFactory::make($country);
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
     * Validates a GLN (ICD 0088): 14 numeric digits with a valid GS1 mod-10
     * check digit. Storecove's own validator uses `^\d{14}$`; we additionally
     * enforce the check digit so transposed/miskeyed values fail fast.
     *
     * Accepts either a bare 14-digit value or the "0088:<14digits>" form.
     */
    public static function isValidGln(string $value): bool
    {
        $value = trim($value);

        if (str_starts_with($value, '0088:')) {
            $value = substr($value, 5);
        }

        if (!ctype_digit($value) || strlen($value) !== 14) {
            return false;
        }

        $sum     = 0;
        $weights = [3, 1];
        for ($i = 12, $j = 0; $i >= 0; $i--, $j++) {
            $sum += ((int) $value[$i]) * $weights[$j % 2];
        }

        return ((10 - ($sum % 10)) % 10) === (int) $value[13];
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
            $examples = array_filter(array_map(fn($s) => $this->getFormatExample($s), $schemes));
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

        $map = config('einvoice.iso6523_map');
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
        } elseif (in_array($country, ['GLN','0087'])) { // handle GLN and 0087 prefix
            return 'routing_id';
        }

        $rules = $this->routing_rules[$country] ?? null;

        if ($rules === null) {
            return '';
        }

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
