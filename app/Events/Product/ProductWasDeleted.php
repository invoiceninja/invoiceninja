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

class ProductWasDeleted
{
    use SerializesModels;

    /**
     * @var Product
     */
    public $product;

    public $company;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Product $product, $company)
    {
        $this->product = $product;
        $this->company = $company;
    }
}
