<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Helpers\Invoice\AggregateProductAllocationToInvoiceItems;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;

class ApplyProductAllocations extends AbstractService
{
    use GeneratesCounter;

    /**
     * Summary of __construct
     * @param \App\Models\Invoice $invoice
     * @param \App\Models\ProductAllocation[] $product_allocations
     */
    public function __construct(private Invoice $invoice, private array $product_allocations)
    {
    }

    public function run()
    {

        $this->invoice = (new AggregateProductAllocationToInvoiceItems($this->invoice, $this->product_allocations))->aggregate();

        $this->invoice->saveQuietly();

        return $this->invoice;
    }
}
