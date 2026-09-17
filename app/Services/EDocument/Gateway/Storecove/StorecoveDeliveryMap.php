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

use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRequiredClientFields;
use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRoutingRules;

class StorecoveDeliveryMap
{
    public function __construct(
        private ?StorecoveRoutingRules $routingRules = null,
        private ?StorecoveRequiredClientFields $requiredClientFields = null,
    ) {
    }

    /**
     * @return array<string, array{
     *   classifications: array<string, bool>,
     *   required_fields: array<string, array<string, string>>
     * }>
     */
    public function all(): array
    {
        $map = [];

        foreach (array_keys($this->routingRules()->all()) as $country) {
            $map[$country] = [
                'classifications' => [
                    'business' => $this->routingRules()->isClassificationRoutable($country, 'business'),
                    'government' => $this->routingRules()->isClassificationRoutable($country, 'government'),
                    'individual' => $this->routingRules()->isClassificationRoutable($country, 'individual'),
                ],
                'required_fields' => [
                    'business' => $this->requiredClientFields()->for($country, 'business'),
                    'government' => $this->requiredClientFields()->for($country, 'government'),
                    'individual' => $this->requiredClientFields()->for($country, 'individual'),
                ],
            ];
        }

        return $map;
    }

    private function routingRules(): StorecoveRoutingRules
    {
        return $this->routingRules ??= new StorecoveRoutingRules();
    }

    private function requiredClientFields(): StorecoveRequiredClientFields
    {
        return $this->requiredClientFields ??= new StorecoveRequiredClientFields($this->routingRules());
    }
}
