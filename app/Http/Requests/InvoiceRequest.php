<?php namespace App\Http\Requests;

class InvoiceRequest extends EntityRequest {

    protected $entityType = ENTITY_INVOICE;

    /*
    public function entity()
    {
        $expense = parent::entity();
        
        // eager load the contacts
        if ($expense && ! count($expense->documents)) {
            $expense->load('documents');
        }
         
        return $expense;
    }
    */
}