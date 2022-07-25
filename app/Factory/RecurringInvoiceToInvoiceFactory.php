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
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Helpers;

class RecurringInvoiceToInvoiceFactory
{
    public static function create(RecurringInvoice $recurring_invoice, Client $client) :Invoice
    {
        $invoice = new Invoice();
        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->discount = $recurring_invoice->discount;
        $invoice->is_amount_discount = $recurring_invoice->is_amount_discount;
        $invoice->po_number = $recurring_invoice->po_number;
        $invoice->footer = self::tranformObject($recurring_invoice->footer, $client);
        $invoice->terms = self::tranformObject($recurring_invoice->terms, $client);
        $invoice->public_notes = self::tranformObject($recurring_invoice->public_notes, $client);
        $invoice->private_notes = $recurring_invoice->private_notes;
        //$invoice->date = now()->format($client->date_format());
        //$invoice->due_date = $recurring_invoice->calculateDueDate(now());
        $invoice->is_deleted = $recurring_invoice->is_deleted;
//        $invoice->line_items = $recurring_invoice->line_items;
        $invoice->line_items = self::transformItems($recurring_invoice, $client);
        $invoice->tax_name1 = $recurring_invoice->tax_name1;
        $invoice->tax_rate1 = $recurring_invoice->tax_rate1;
        $invoice->tax_name2 = $recurring_invoice->tax_name2;
        $invoice->tax_rate2 = $recurring_invoice->tax_rate2;
        $invoice->tax_name3 = $recurring_invoice->tax_name3;
        $invoice->tax_rate3 = $recurring_invoice->tax_rate3;
        $invoice->total_taxes = $recurring_invoice->total_taxes;
        $invoice->subscription_id = $recurring_invoice->subscription_id;
        $invoice->custom_value1 = $recurring_invoice->custom_value1;
        $invoice->custom_value2 = $recurring_invoice->custom_value2;
        $invoice->custom_value3 = $recurring_invoice->custom_value3;
        $invoice->custom_value4 = $recurring_invoice->custom_value4;
        $invoice->amount = $recurring_invoice->amount;
        $invoice->uses_inclusive_taxes = $recurring_invoice->uses_inclusive_taxes;

        $invoice->custom_surcharge1 = $recurring_invoice->custom_surcharge1;
        $invoice->custom_surcharge2 = $recurring_invoice->custom_surcharge2;
        $invoice->custom_surcharge3 = $recurring_invoice->custom_surcharge3;
        $invoice->custom_surcharge4 = $recurring_invoice->custom_surcharge4;
        $invoice->custom_surcharge_tax1 = $recurring_invoice->custom_surcharge_tax1;
        $invoice->custom_surcharge_tax2 = $recurring_invoice->custom_surcharge_tax2;
        $invoice->custom_surcharge_tax3 = $recurring_invoice->custom_surcharge_tax3;
        $invoice->custom_surcharge_tax4 = $recurring_invoice->custom_surcharge_tax4;

        // $invoice->balance = $recurring_invoice->balance;
        $invoice->user_id = $recurring_invoice->user_id;
        $invoice->assigned_user_id = $recurring_invoice->assigned_user_id;
        $invoice->company_id = $recurring_invoice->company_id;
        $invoice->recurring_id = $recurring_invoice->id;
        $invoice->client_id = $client->id;
        $invoice->auto_bill_enabled = $recurring_invoice->auto_bill_enabled;
        $invoice->paid_to_date = 0;
        $invoice->design_id = $recurring_invoice->design_id;

        return $invoice;
    }

    private static function transformItems($recurring_invoice, $client)
    {
        $line_items = $recurring_invoice->line_items;

        foreach ($line_items as $key => $item) {
            if (property_exists($line_items[$key], 'notes')) {
                $line_items[$key]->notes = Helpers::processReservedKeywords($item->notes, $client);
            }
        }

        return $line_items;
    }

    private static function tranformObject($object, $client)
    {
        return Helpers::processReservedKeywords($object, $client);
    }
}
