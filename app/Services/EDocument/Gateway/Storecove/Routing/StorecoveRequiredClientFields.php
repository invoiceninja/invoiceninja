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
namespace App\Services\EDocument\Gateway\Storecove\Routing;

class StorecoveRequiredClientFields
{
    public function __construct(private ?StorecoveRoutingRules $routingRules = null)
    {
    }

    /**
     * @return array<string, string>
     */
    public function for(string $country, ?string $classification = 'business', ?string $senderCountryCode = null): array
    {
        $classification ??= 'business';

        if ($country === 'IT' && $classification === 'individual') {
            $fields = ['id_number' => 'IT:CF'];

            if ($senderCountryCode === 'IT') {
                $fields['routing_id'] = 'IT:CUUO';
            }

            return $fields;
        }

        if ($classification === 'individual') {
            return [];
        }

        $required = $this->fromRoutingRule($country, $classification);

        if ($country === 'IT' && in_array($classification, ['business', 'government'], true)) {
            $required['routing_id'] = 'IT:CUUO';
        }

        return $required;
    }

    /**
     * @return array<string, string>
     */
    public function fromRoutingRule(string $country, ?string $classification): array
    {
        $rule = $this->routingRules()->ruleFor($country, $classification);

        if ($rule === null) {
            return [];
        }

        $required = [];

        if (!empty($rule[StorecoveRoutingRules::COL_TAX_IDENTIFIER])) {
            $required['vat_number'] = (string) $rule[StorecoveRoutingRules::COL_TAX_IDENTIFIER];
        }

        if (!empty($rule[StorecoveRoutingRules::COL_LEGAL_IDENTIFIER])) {
            $required['id_number'] = (string) $rule[StorecoveRoutingRules::COL_LEGAL_IDENTIFIER];
        }

        return $required;
    }

    private function routingRules(): StorecoveRoutingRules
    {
        return $this->routingRules ??= new StorecoveRoutingRules();
    }
}
