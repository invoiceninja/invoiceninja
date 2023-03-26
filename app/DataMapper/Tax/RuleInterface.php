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

use App\Models\Client;
use App\DataMapper\Tax\ZipTax\Response;

interface RuleInterface
{
    public function tax();

    public function taxByType(?int $type);

    public function taxExempt();
    
    public function taxDigital();

    public function taxService();

    public function taxShipping();

    public function taxPhysical();

    public function taxReduced();

    public function default();

    public function setClient(Client $client);

    public function setTaxData(Response $tax_data);
}