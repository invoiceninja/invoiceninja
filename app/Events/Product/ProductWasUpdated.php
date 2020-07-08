<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Product;

use App\Models\Product;
use Illuminate\Queue\SerializesModels;

class ProductWasUpdated
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
     * @return void
     */
    public function __construct(Product $product, Company $company, array $event_vars)
    {
        $this->product = $product;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
