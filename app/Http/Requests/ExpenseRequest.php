<?php namespace App\Http\Requests;

class ExpenseRequest extends EntityRequest {

    protected $entityType = ENTITY_EXPENSE;

    public function entity()
    {
        $expense = parent::entity();
        
        // eager load the documents
        if ($expense && ! $expense->relationLoaded('documents')) {
            $expense->load('documents');
        }
        
        return $expense;
    }
}