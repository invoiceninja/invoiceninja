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

use App\Models\Invoice;
use App\Models\Quote;

class CloneQuoteToInvoiceFactory
{
    public static function create(Quote $quote, $user_id) : ?Invoice
    {
        $invoice = new Invoice();

        $quote_array = $quote->toArray();

        unset($quote_array['client']);
        unset($quote_array['company']);
        unset($quote_array['hashed_id']);
        unset($quote_array['invoice_id']);
        unset($quote_array['id']);
        unset($quote_array['invitations']);

        //preserve terms if they exist on Quotes
        //if(array_key_exists('terms', $quote_array) && strlen($quote_array['terms']) < 2)
        if (! $quote->company->use_quote_terms_on_conversion) {
            unset($quote_array['terms']);
        }

        // unset($quote_array['public_notes']);
        unset($quote_array['footer']);
        unset($quote_array['design_id']);
        unset($quote_array['user']);

        foreach ($quote_array as $key => $value) {
            $invoice->{$key} = $value;
        }

        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->due_date = null;
        $invoice->partial_due_date = null;
        $invoice->number = null;
        $invoice->date = now()->format('Y-m-d');
        $invoice->balance = 0;
        $invoice->deleted_at = null;
        $invoice->next_send_date = null;
        $invoice->reminder1_sent = null;
        $invoice->reminder2_sent = null;
        $invoice->reminder3_sent = null;
        $invoice->reminder_last_sent = null;
        $invoice->last_sent_date = null;
        $invoice->last_viewed = null;

        return $invoice;
    }
}
