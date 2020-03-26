<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Libraries\HistoryUtils;

class InvoiceRequest extends EntityRequest
{
    protected $entityType = ENTITY_INVOICE;

        /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $invoice = parent::entity();

        if ($invoice && $invoice->isQuote())
            $standardOrRecurringInvoice = ENTITY_QUOTE;
        elseif($invoice && $invoice->is_recurring)
            $standardOrRecurringInvoice = ENTITY_RECURRING_INVOICE;
        else
            $standardOrRecurringInvoice = ENTITY_INVOICE;

        if(request()->is('invoices/*/edit') && request()->isMethod('get') && $this->user()->can('edit', $invoice))
            return true;

        if(request()->is('quotes/*/edit') && request()->isMethod('get') && $this->user()->can('edit', $invoice))
            return true;

        if(request()->is('invoices/create*') && $this->user()->can('create', ENTITY_INVOICE))
            return true;

        if(request()->is('invoices/create*') && !$this->user()->can('create', ENTITY_INVOICE))
            return false;

        if(request()->is('recurring_invoices/create') && !$this->user()->can('create', ENTITY_RECURRING_INVOICE))
            return false;

        if(request()->is('quotes/create*') && !$this->user()->can('create', ENTITY_QUOTE))
            return false;

        if(request()->is('invoices/*/edit') && request()->isMethod('put') && !$this->user()->can('edit', $standardOrRecurringInvoice))
            return false;

        if(request()->is('quotes/*/edit') && request()->isMethod('put') && !$this->user()->can('edit', ENTITY_QUOTE))
            return false;

        if(request()->is('invoices/*') && request()->isMethod('get') && !$this->user()->can('view', $standardOrRecurringInvoice))
            return false;

        if(request()->is('quotes/*') && request()->isMethod('get') && !$this->user()->can('view', ENTITY_QUOTE))
            return false;

        if ($invoice) {
            HistoryUtils::trackViewed($invoice);
        }

        return true;
    }

    public function entity()
    {
        $invoice = parent::entity();

        // support loading an invoice by its invoice number
        if ($this->invoice_number && ! $invoice) {
            $invoice = Invoice::scope()
                        ->whereInvoiceNumber($this->invoice_number)
                        ->withTrashed()
                        ->first();

            if (! $invoice) {
                abort(404);
            }
        }

        // eager load the invoice items
        if ($invoice && ! $invoice->relationLoaded('invoice_items')) {
            $invoice->load('invoice_items');
        }

        return $invoice;
    }
}
