<?php
namespace App\Factory;

use App\Models\Invoice;
use App\Models\Quote;

class CloneQuoteToInvoiceFactory
{
    public static function create(Quote $quote, $user_id) : ?Invoice
    {
        $invoice = new Invoice();
        $invoice->user_id = $user_id;
        $invoice->po_number = $quote->po_number;
        $invoice->footer = $quote->footer;
        $invoice->line_items = $quote->line_items;
        
        return $invoice;
    }
}
