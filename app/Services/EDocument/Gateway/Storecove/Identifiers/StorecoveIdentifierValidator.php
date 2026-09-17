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
namespace App\Services\EDocument\Gateway\Storecove\Identifiers;

class StorecoveIdentifierValidator
{
    public function __construct(
        private ?array $identifierRegex = null,
        private ?array $identifierFormatExamples = null,
    ) {
    }

    /**
     * @param  bool  $checkDigit  When false, only the structural regex is
     *                             enforced and the scheme's check digit (e.g.
     *                             SIREN/SIRET Luhn, BE mod-97) is skipped.
     *                             Used by client-level validation, which must
     *                             not block on a check digit - that is enforced
     *                             strictly on the registration/send path.
     */
    public function validFormat(string $scheme, string $value, bool $checkDigit = true): bool
    {
        if (stripos($scheme, ' or ') !== false) {
            foreach (array_map('trim', explode(' or ', $scheme)) as $atomicScheme) {
                if ($this->validFormat($atomicScheme, $value, $checkDigit)) {
                    return true;
                }
            }

            return false;
        }

        if (stripos($scheme, ',') !== false) {
            return strlen($value) >= 2;
        }

        if (stripos($scheme, ' + ') !== false) {
            return strlen(preg_replace("/[\s.\-]/", '', $value)) >= 2;
        }

        $cleanValue = self::dashSignificantScheme($scheme)
            ? preg_replace('/\s+/', '', $value)
            : preg_replace("/[\s.\-]/", '', $value);

        $regex = $this->regex();

        if (!isset($regex[$scheme])) {
            return strlen($cleanValue) >= 2;
        }

        if (!preg_match($regex[$scheme], $cleanValue)) {
            return false;
        }

        if (!$checkDigit) {
            return true;
        }

        return $this->checkdigit($scheme, $cleanValue) !== false;
    }

    public function matchesSchemeFormat(string $scheme, string $value): bool
    {
        $regex = $this->regex();

        if (!isset($regex[$scheme])) {
            return strlen($value) >= 2;
        }

        return (bool) preg_match($regex[$scheme], $value);
    }

    public function validCheckdigit(string $scheme, string $value): ?bool
    {
        return $this->checkdigit($scheme, preg_replace("/[\s.\-]/", '', $value));
    }

    public function formatExample(string $scheme): ?string
    {
        if (stripos($scheme, ' or ') !== false) {
            $examples = array_filter(
                array_map(fn (string $atomicScheme): ?string => $this->formatExample($atomicScheme), array_map('trim', explode(' or ', $scheme)))
            );

            return $examples !== [] ? implode(' or ', $examples) : null;
        }

        return $this->examples()[$scheme] ?? null;
    }

    public static function dashSignificantScheme(string $scheme): bool
    {
        return $scheme === 'DE:LWID';
    }

    private function checkdigit(string $scheme, string $cleanValue): ?bool
    {
        return match ($scheme) {
            'BE:EN' => $this->mod97Check($this->stripCountryPrefix($cleanValue, 'BE')),
            'BE:VAT' => $this->mod97Check($this->stripCountryPrefix($cleanValue, 'BE')),
            'FR:SIRENE' => $this->frenchLuhnCheck($cleanValue),
            'FR:SIRET' => $this->frenchLuhnCheck($cleanValue),
            default => null,
        };
    }

    /**
     * SIREN (9 digits) and SIRET (14 digits) carry a Luhn check digit.
     *
     * Exception: La Poste establishments (SIREN 356000000) are not Luhn-valid;
     * for those a SIRET is valid when the sum of its digits is divisible by 5.
     */
    private function frenchLuhnCheck(string $digits): bool
    {
        if (!ctype_digit($digits) || !in_array(strlen($digits), [9, 14], true)) {
            return false;
        }

        if (strlen($digits) === 14 && str_starts_with($digits, '356000000')) {
            return array_sum(str_split($digits)) % 5 === 0;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];

            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }

            $sum += $d;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    private function mod97Check(string $digits): bool
    {
        if (strlen($digits) !== 10 || !ctype_digit($digits)) {
            return false;
        }

        $body = (int) substr($digits, 0, 8);
        $check = (int) substr($digits, 8, 2);

        return (97 - ($body % 97)) === $check;
    }

    private function stripCountryPrefix(string $value, string $prefix): string
    {
        if (stripos($value, $prefix) === 0) {
            return substr($value, strlen($prefix));
        }

        return $value;
    }

    private function regex(): array
    {
        $regex = $this->identifierRegex ?? config('einvoice.identifier_regex', []);

        return is_array($regex) ? $regex : [];
    }

    private function examples(): array
    {
        $examples = $this->identifierFormatExamples ?? config('einvoice.identifier_format_examples', []);

        return is_array($examples) ? $examples : [];
    }
}
