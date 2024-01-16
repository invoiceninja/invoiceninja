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

use App\Models\Quote;

class QuoteFactory
{
    public static function create(int $company_id, int $user_id): Quote
    {
        $quote = new Quote();
        $quote->status_id = Quote::STATUS_DRAFT;
        $quote->number = null;
        $quote->discount = 0;
        $quote->is_amount_discount = true;
        $quote->po_number = '';
        $quote->footer = '';
        $quote->terms = '';
        $quote->public_notes = '';
        $quote->private_notes = '';
        $quote->date = now()->format('Y-m-d');
        $quote->due_date = null;
        $quote->partial_due_date = null;
        $quote->is_deleted = false;
        $quote->line_items = json_encode([]);
        $quote->tax_name1 = '';
        $quote->tax_rate1 = 0;
        $quote->tax_name2 = '';
        $quote->tax_rate2 = 0;
        $quote->custom_value1 = '';
        $quote->custom_value2 = '';
        $quote->custom_value3 = '';
        $quote->custom_value4 = '';
        $quote->amount = 0;
        $quote->balance = 0;
        $quote->partial = 0;
        $quote->user_id = $user_id;
        $quote->company_id = $company_id;
        $quote->paid_to_date = 0;
        $quote->exchange_rate = 1;

        return $quote;
    }
}
