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

namespace App\Factory;

use App\Models\Credit;
use App\Models\Quote;

class CloneCreditToQuoteFactory
{
    public static function create(Credit $credit, $user_id): ?Quote
    {
        $quote = new Quote();
        $quote->client_id = $credit->client_id;
        $quote->user_id = $user_id;
        $quote->company_id = $credit->company_id;
        $quote->discount = $credit->discount;
        $quote->is_amount_discount = $credit->is_amount_discount;
        $quote->po_number = $credit->po_number;
        $quote->is_deleted = false;
        $quote->footer = $credit->footer;
        $quote->public_notes = $credit->public_notes;
        $quote->private_notes = $credit->private_notes;
        $quote->terms = $credit->terms;
        $quote->tax_name1 = $credit->tax_name1;
        $quote->tax_rate1 = $credit->tax_rate1;
        $quote->tax_name2 = $credit->tax_name2;
        $quote->tax_rate2 = $credit->tax_rate2;
        $quote->custom_value1 = $credit->custom_value1;
        $quote->custom_value2 = $credit->custom_value2;
        $quote->custom_value3 = $credit->custom_value3;
        $quote->custom_value4 = $credit->custom_value4;
        $quote->amount = $credit->amount;
        //$quote->balance = $credit->balance;
        $quote->partial = $credit->partial;
        $quote->partial_due_date = $credit->partial_due_date;
        $quote->last_viewed = $credit->last_viewed;

        $quote->status_id = Quote::STATUS_DRAFT;
        $quote->number = '';
        $quote->date = null;
        $quote->due_date = null;
        $quote->partial_due_date = null;
        // $quote->balance = $credit->amount;
        $quote->line_items = $credit->line_items;

        return $quote;
    }
}
