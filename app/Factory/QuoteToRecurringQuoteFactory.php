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
use App\Models\RecurringQuote;

class QuoteToRecurringQuoteFactory
{
    public static function create(Quote $quote): RecurringQuote
    {
        $recurring_quote = new RecurringQuote();

        $recurring_quote->status_id = RecurringQuote::STATUS_DRAFT;
        $recurring_quote->discount = $quote->discount;
        $recurring_quote->number = '';
        $recurring_quote->is_amount_discount = $quote->is_amount_discount;
        $recurring_quote->po_number = $quote->po_number;
        $recurring_quote->footer = $quote->footer;
        $recurring_quote->terms = $quote->terms;
        $recurring_quote->public_notes = $quote->public_notes;
        $recurring_quote->private_notes = $quote->private_notes;
        $recurring_quote->date = date_create()->format($quote->client->date_format());
        $recurring_quote->due_date = $quote->due_date; //todo calculate based on terms
        $recurring_quote->is_deleted = $quote->is_deleted;
        $recurring_quote->line_items = $quote->line_items;
        $recurring_quote->tax_name1 = $quote->tax_name1;
        $recurring_quote->tax_rate1 = $quote->tax_rate1;
        $recurring_quote->tax_name2 = $quote->tax_name2;
        $recurring_quote->tax_rate2 = $quote->tax_rate2;
        $recurring_quote->custom_value1 = $quote->custom_value1;
        $recurring_quote->custom_value2 = $quote->custom_value2;
        $recurring_quote->custom_value3 = $quote->custom_value3;
        $recurring_quote->custom_value4 = $quote->custom_value4;
        $recurring_quote->amount = $quote->amount;
        // $recurring_quote->balance = $quote->balance;
        $recurring_quote->user_id = $quote->user_id;
        $recurring_quote->client_id = $quote->client_id;
        $recurring_quote->company_id = $quote->company_id;
        $recurring_quote->frequency_id = RecurringQuote::FREQUENCY_MONTHLY;
        $recurring_quote->last_sent_date = null;
        $recurring_quote->next_send_date = null;
        $recurring_quote->remaining_cycles = 0;
        $recurring_quote->paid_to_date = 0;

        return $recurring_quote;
    }
}
