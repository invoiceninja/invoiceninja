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

use App\Helpers\ProductAllocation\AggregateProductAllocationToInvoiceItems;
use App\Jobs\ProductAllocation\UpdateOrCreateProductAllocation;
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

        UpdateOrCreateProductAllocation::dispatch($this->invoice->line_items, $this->invoice, $this->invoice->company);

        $this->invoice->saveQuietly();

        return $this->invoice;

    }
}
