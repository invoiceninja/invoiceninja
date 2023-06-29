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

namespace App\Services\Recurring;

use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class IncreasePrice extends AbstractService
{
    public function __construct(public RecurringInvoice $recurring_invoice, public float $percentage)
    {
    }

    public function run()
    {
        $line_items = $this->recurring_invoice->line_items;
        foreach ($line_items as $key => $line_item) {
            $line_items[$key]->cost = $line_item->cost * (1 + round(($this->percentage / 100), 2));
        }

        $this->recurring_invoice->line_items = $line_items;
        $this->recurring_invoice->calc()->getInvoice()->save();
    }
}
