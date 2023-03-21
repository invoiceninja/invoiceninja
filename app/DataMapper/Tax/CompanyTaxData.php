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

use App\DataMapper\Tax\ZipTax\Response;

/**
 * CompanyTaxData
 * 
 * Definition for the company tax data structure
 */
class CompanyTaxData
{
    public int $updated_at;
    
    public function __construct(public Response $origin)
    {
    }
}
