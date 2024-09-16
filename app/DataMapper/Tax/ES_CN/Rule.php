<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax\ES_CN;

use App\DataMapper\Tax\DE\Rule as DERule;

class Rule extends DERule
{
    /** @var string $seller_region */
    public string $seller_region = 'EU';

    /** @var bool $consumer_tax_exempt */
    public bool $consumer_tax_exempt = false;

    /** @var bool $business_tax_exempt */
    public bool $business_tax_exempt = false;

    /** @var bool $eu_business_tax_exempt */
    public bool $eu_business_tax_exempt = true;

    /** @var bool $foreign_business_tax_exempt */
    public bool $foreign_business_tax_exempt = false;

    /** @var bool $foreign_consumer_tax_exempt */
    public bool $foreign_consumer_tax_exempt = false;

    /** @var float $tax_rate */
    public float $tax_rate = 0;

    /** @var float $reduced_tax_rate */
    public float $reduced_tax_rate = 0;

    public string $tax_name1 = 'IGIC';

}
