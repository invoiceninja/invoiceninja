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
        $entity  = $invoice ? $invoice->subEntityType() : ENTITY_INVOICE;

        switch($entity)
        {
            case ENTITY_INVOICE:
                $crossCloneEntity = ENTITY_QUOTE;
                break;
            case ENTITY_QUOTE:
                $crossCloneEntity = ENTITY_INVOICE;
                break;
            case ENTITY_RECURRING_INVOICE:
                $crossCloneEntity = ENTITY_RECURRING_QUOTE;
                break;
            case ENTITY_RECURRING_QUOTE:
                $crossCloneEntity = ENTITY_RECURRING_INVOICE;
                break;
        }

        if(request()->is('invoices/create*') && $this->user()->can('createEntity', ENTITY_INVOICE))
            return true;

        if(request()->is('recurring_invoices/create*') && $this->user()->can('createEntity', ENTITY_INVOICE))
            return true;

        if(request()->is('quotes/create*') && $this->user()->can('createEntity', ENTITY_QUOTE))
            return true;

        if(request()->is('recurring_quotes/create*') && $this->user()->can('createEntity', ENTITY_QUOTE))
            return true;

        if($invoice && $invoice->isType(INVOICE_TYPE_STANDARD) && request()->is('*invoices/*/edit') && request()->isMethod('put') && $this->user()->can('edit', $invoice))
            return true;

        if($invoice && $invoice->isType(INVOICE_TYPE_QUOTE) && request()->is('*quotes/*/edit') && request()->isMethod('put') && $this->user()->can('edit', $invoice))
            return true;

        // allow cross clone quote to invoice
        if($invoice && $invoice->isType(INVOICE_TYPE_QUOTE) && request()->is('*invoices/*/clone') && request()->isMethod('get') && $this->user()->can('view', $invoice, $crossCloneEntity))
            return true;

        // allow cross clone invoice to quote
        if($invoice && $invoice->isType(INVOICE_TYPE_STANDARD) && request()->is('*quotes/*/clone') && request()->isMethod('get') && $this->user()->can('view', $invoice, $crossCloneEntity))
            return true;

        if($invoice && $invoice->isType(INVOICE_TYPE_STANDARD) && request()->is('*invoices/*') && request()->isMethod('get') && $this->user()->can('view', $invoice, $entity))
            return true;

        if($invoice && $invoice->isType(INVOICE_TYPE_QUOTE) && request()->is('*quotes/*') && request()->isMethod('get') && $this->user()->can('view', $invoice, $entity))
            return true;

        if ($invoice) {
            HistoryUtils::trackViewed($invoice);
        }

        return false;
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
