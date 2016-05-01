<?php namespace App\Http\Requests;

class InvoiceRequest extends EntityRequest {

    protected $entityType = ENTITY_INVOICE;

    public function entity()
    {
        $invoice = parent::entity();
        
        // eager load the contacts
        if ($invoice && ! count($invoice->invoice_items)) {
            $invoice->load('invoice_items');
        }
         
        return $invoice;
    }

}