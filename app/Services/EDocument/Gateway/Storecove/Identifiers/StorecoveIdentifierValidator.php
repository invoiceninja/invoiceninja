<?php

namespace App\Services\EDocument\Gateway\Storecove\Identifiers;

class StorecoveIdentifierValidator
{
    public function __construct(
        private ?array $identifierRegex = null,
        private ?array $identifierFormatExamples = null,
    ) {
    }

    public function validFormat(string $scheme, string $value): bool
    {
        if (stripos($scheme, ' or ') !== false) {
            foreach (array_map('trim', explode(' or ', $scheme)) as $atomicScheme) {
                if ($this->validFormat($atomicScheme, $value)) {
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
            default => null,
        };
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
