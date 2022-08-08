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

namespace App\Factory;

use App\Models\Client;
use App\Models\Quote;
use App\Models\RecurringQuote;

class RecurringQuoteToQuoteFactory
{
    public static function create(RecurringQuote $recurring_quote, Client $client) :Quote
    {
        $quote = new Quote();
        $quote->status_id = Quote::STATUS_DRAFT;
        $quote->discount = $recurring_quote->discount;
        $quote->is_amount_discount = $recurring_quote->is_amount_discount;
        $quote->po_number = $recurring_quote->po_number;
        $quote->footer = $recurring_quote->footer;
        $quote->terms = $recurring_quote->terms;
        $quote->public_notes = $recurring_quote->public_notes;
        $quote->private_notes = $recurring_quote->private_notes;
        //$quote->date = now()->format($client->date_format());
        //$quote->due_date = $recurring_quote->calculateDueDate(now());
        $quote->is_deleted = $recurring_quote->is_deleted;
        $quote->line_items = $recurring_quote->line_items;
        $quote->tax_name1 = $recurring_quote->tax_name1;
        $quote->tax_rate1 = $recurring_quote->tax_rate1;
        $quote->tax_name2 = $recurring_quote->tax_name2;
        $quote->tax_rate2 = $recurring_quote->tax_rate2;
        $quote->tax_name3 = $recurring_quote->tax_name3;
        $quote->tax_rate3 = $recurring_quote->tax_rate3;
        $quote->total_taxes = $recurring_quote->total_taxes;
        $quote->subscription_id = $recurring_quote->subscription_id;
        $quote->custom_value1 = $recurring_quote->custom_value1;
        $quote->custom_value2 = $recurring_quote->custom_value2;
        $quote->custom_value3 = $recurring_quote->custom_value3;
        $quote->custom_value4 = $recurring_quote->custom_value4;
        $quote->amount = $recurring_quote->amount;
        // $quote->balance = $recurring_quote->balance;
        $quote->user_id = $recurring_quote->user_id;
        $quote->assigned_user_id = $recurring_quote->assigned_user_id;
        $quote->company_id = $recurring_quote->company_id;
        $quote->recurring_id = $recurring_quote->id;
        $quote->client_id = $client->id;
        $quote->auto_bill_enabled = $recurring_quote->auto_bill_enabled;
        $quote->paid_to_date = 0;
        $quote->design_id = $recurring_quote->design_id;

        return $quote;
    }
}
