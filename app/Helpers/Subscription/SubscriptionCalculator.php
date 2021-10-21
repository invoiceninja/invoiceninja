<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Subscription;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;

/**
 * SubscriptionCalculator.
 */
class SubscriptionCalculator 
{
    public Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Tests if the user is currently up
     * to date with their payments for
     * a given recurring invoice
     *     
     * @return bool
     */
    public function isPaidUp(RecurringInvoice $recurring_invoice) :bool
    {

        $outstanding_invoices_exist = Invoice::whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                               ->where('recurring_id', $recurring_invoice->id)
                                               ->where('balance', '>', 0)
                                               ->exists();

       return ! $outstanding_invoices_exist;

    }
}