<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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

    /**
     * @var Product
     */
    public $invoice;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Product $invoice
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Product $product, $fromDeleted, Company $company, array $event_vars)
    {
        $this->product = $product;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
