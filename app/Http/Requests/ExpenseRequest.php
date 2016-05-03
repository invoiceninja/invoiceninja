<?php namespace App\Http\Requests;

class ExpenseRequest extends EntityRequest {

    protected $entityType = ENTITY_EXPENSE;

    public function entity()
    {
        $expense = parent::entity();
        
        // eager load the contacts
        if ($expense && ! count($expense->documents)) {
            $expense->load('documents');
        }
         
        return $expense;
    }
}