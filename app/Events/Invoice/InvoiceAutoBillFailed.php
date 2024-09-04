<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceAutoBillFailed.
 */
class InvoiceAutoBillFailed
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public Invoice $invoice, public Company $company, public array $event_vars, public ?string $notes)
    {
    }
}
