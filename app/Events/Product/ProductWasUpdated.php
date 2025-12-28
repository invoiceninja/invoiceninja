<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Product;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Queue\SerializesModels;

class ProductWasUpdated
{
    use SerializesModels;

    public function __construct(public Product $product, public Company $company, public array $event_vars)
    {
    }
}
