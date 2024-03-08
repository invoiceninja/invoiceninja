<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax;

interface RuleInterface
{
    public function init();

    public function tax($item);

    public function taxByType($type);

    public function taxExempt($item);

    public function taxDigital($item);

    public function taxService($item);

    public function taxShipping($item);

    public function taxPhysical($item);

    public function taxReduced($item);

    public function default($item);

    public function override($item);

    public function calculateRates();

    public function regionWithNoTaxCoverage(string $iso_3166_2): bool;

    public function setEntity($entity): self;

    public function shouldCalcTax(): bool;
}
