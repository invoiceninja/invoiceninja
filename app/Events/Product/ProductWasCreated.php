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

namespace App\Events\Product;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Queue\SerializesModels;

class ProductWasCreated
{
    use SerializesModels;

    /**
     * @var Product
     */
    public $product;

    public $input;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Product $product
     * @param $input
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Product $product, $input, Company $company, array $event_vars)
    {
        $this->product = $product;
        $this->input = $input;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
