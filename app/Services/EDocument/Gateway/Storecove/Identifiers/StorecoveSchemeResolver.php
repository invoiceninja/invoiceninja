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

use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRoutingRules;

class StorecoveSchemeResolver
{
    public function __construct(
        private ?array $iso6523Map = null,
        private ?StorecoveRoutingRules $routingRules = null,
    ) {
    }

    public function iso6523(string $scheme): string
    {
        if (ctype_digit($scheme)) {
            return $scheme;
        }

        $map = $this->map();

        if (isset($map[$scheme])) {
            return $map[$scheme];
        }

        if (stripos($scheme, ' or ') !== false) {
            foreach (array_map('trim', explode(' or ', $scheme)) as $atomicScheme) {
                if (isset($map[$atomicScheme])) {
                    return $map[$atomicScheme];
                }
            }
        }

        return $scheme;
    }

    public function publicIdentifierField(string $identifier): string
    {
        $parts = explode(':', $identifier);
        $country = $parts[0];

        if ($country === 'LEI') {
            $country = 'BE';
            $identifier = 'BE:VAT';
        } elseif (in_array($country, ['GLN', '0087'], true)) {
            return 'routing_id';
        }

        $rules = $this->routingRules()->all()[$country] ?? null;

        if (!is_array($rules)) {
            return '';
        }

        foreach ($this->rows($rules) as $row) {
            $taxScheme = $row[StorecoveRoutingRules::COL_TAX_IDENTIFIER] ?? null;
            $routingScheme = $row[StorecoveRoutingRules::COL_ROUTING_IDENTIFIER] ?? null;

            if (!empty($taxScheme) && stripos($identifier, (string) $taxScheme) !== false) {
                return 'vat_number';
            }

            if (!empty($routingScheme) && stripos($identifier, (string) $routingScheme) !== false) {
                return 'id_number';
            }
        }

        return '';
    }

    private function map(): array
    {
        $map = $this->iso6523Map ?? config('einvoice.iso6523_map', []);

        return is_array($map) ? $map : [];
    }

    private function routingRules(): StorecoveRoutingRules
    {
        return $this->routingRules ??= new StorecoveRoutingRules();
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, array<int, mixed>>
     */
    private function rows(array $rules): array
    {
        return isset($rules[0]) && is_array($rules[0]) ? $rules : [$rules];
    }
}
