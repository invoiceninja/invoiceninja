<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Product;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Queue\SerializesModels;

/**
 * Class ProductWasRestored.
 */
class ProductWasRestored
{
    use SerializesModels;

    public function __construct(public Product $product, public bool $fromDeleted, public Company $company, public array $event_vars)
    {
    }
}
