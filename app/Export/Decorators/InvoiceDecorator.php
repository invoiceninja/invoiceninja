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

namespace App\Export\Decorators;

use App\Models\Invoice;

class InvoiceDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $invoice = false;

        if($entity instanceof Invoice) {
            $invoice = $entity;
        } elseif($entity->invoice) {
            $invoice = $entity->invoice;
        }

        if($invoice && method_exists($this, $key)) {
            return $this->{$key}($invoice);
        }

        return '';

    }


        public function number(Invoice $invoice) {
            return $invoice->number ?? '';
        }
        public function amount(Invoice $invoice) {
            return $invoice->amount ?? 0;
        }
        public function balance(Invoice $invoice) {
            return $invoice->balance ?? 0;
        }
        public function paid_to_date(Invoice $invoice) {
            return $invoice->paid_to_date ?? 0;
        }
        public function po_number(Invoice $invoice) {
            return $invoice->po_number ?? '';
        }
        public function date(Invoice $invoice) {
            return $invoice->date ?? '';
        }
        public function due_date(Invoice $invoice) {
            return $invoice->due_date ?? '';
        }
        public function terms(Invoice $invoice) {
            return $invoice->terms ?? '';
        }
        public function footer(Invoice $invoice) {
            return $invoice->footer ?? '';
        }
        public function status(Invoice $invoice) {
            return $invoice->stringStatus($invoice->status_id);
        }
        public function public_notes(Invoice $invoice) {
            return $invoice->public_notes ?? '';
        }
        public function private_notes(Invoice $invoice) {
            return $invoice->private_notes ?? '';
        }
        public function uses_inclusive_taxes(Invoice $invoice) {
            return $invoice->uses_inclusive_taxes ? ctrans('texts.yes') : ctrans('texts.no');        
        }
        public function is_amount_discount(Invoice $invoice) {
            return $invoice->is_amount_discount ? ctrans('texts.yes') : ctrans('texts.no');
        }
        public function discount(Invoice $invoice) {
            return $invoice->discount ?? 0;
        }
        public function partial(Invoice $invoice) {
            return $invoice->partial ?? 0;
        }
        public function partial_due_date(Invoice $invoice) {
            return $invoice->partial_due_date ?? '';
        }
        public function custom_surcharge1(Invoice $invoice) {
            return $invoice->custom_surcharge1 ?? 0;
        }
        public function custom_surcharge2(Invoice $invoice) {
            return $invoice->custom_surcharge2 ?? 0;
        }
        public function custom_surcharge3(Invoice $invoice) {
            return $invoice->custom_surcharge3 ?? 0;
        }
        public function custom_surcharge4(Invoice $invoice) {
            return $invoice->custom_surcharge4 ?? 0;
        }
        public function exchange_rate(Invoice $invoice) {
            return $invoice->exchange_rate ?? 0;
        }
        public function total_taxes(Invoice $invoice) {
            return $invoice->total_taxes ?? 0;
        }
        public function assigned_user_id(Invoice $invoice) {
            return $invoice->assigned_user ? $invoice->assigned_user->present()->name(): '';
        }
        public function user_id(Invoice $invoice) {
            return $invoice->user ? $invoice->user->present()->name(): '';
        }
        public function custom_value1(Invoice $invoice) {
            return $invoice->custom_value1 ?? '';
        }
        public function custom_value2(Invoice $invoice) {
            return $invoice->custom_value2 ?? '';
        }
        public function custom_value3(Invoice $invoice) {
            return $invoice->custom_value3 ?? '';
        }
        public function custom_value4(Invoice $invoice) {
            return $invoice->custom_value4 ?? '';
        }
        public function tax_name1(Invoice $invoice) {
            return $invoice->tax_name1 ?? '';
        }
        public function tax_name2(Invoice $invoice) {
            return $invoice->tax_name2 ?? '';
        }
        public function tax_name3(Invoice $invoice) {
            return $invoice->tax_name3 ?? '';
        }
        public function tax_rate1(Invoice $invoice) {
            return $invoice->tax_rate1 ?? 0;
        }
        public function tax_rate2(Invoice $invoice) {
            return $invoice->tax_rate2 ?? 0;
        }
        public function tax_rate3(Invoice $invoice) {
            return $invoice->tax_rate3 ?? 0;
        }
        public function recurring_id(Invoice $invoice) {
            return $invoice->recurring_invoice ? $invoice->recurring_invoice->number : '';
        }
        public function auto_bill_enabled(Invoice $invoice) {
            return $invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
        }

}


