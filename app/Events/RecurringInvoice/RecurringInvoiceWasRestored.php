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

namespace App\Events\RecurringInvoice;

use App\Models\Company;
use App\Models\RecurringInvoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class RecurringInvoiceWasRestored.
 */
class RecurringInvoiceWasRestored
{
    use SerializesModels;

    public function __construct(public RecurringInvoice $recurring_invoice, public bool $fromDeleted, public Company $company, public array $event_vars)
    {
    }
}
