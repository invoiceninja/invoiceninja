<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Product;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Queue\SerializesModels;

class ProductWasDeleted
{
    use SerializesModels;

    /**
     * @var Product
     */
    public $product;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Product $product
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Product $product, Company $company, array $event_vars)
    {
        $this->product = $product;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
