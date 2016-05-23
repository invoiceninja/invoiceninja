<?php namespace App\Ninja\Presenters;

use Utils;

class ExpensePresenter extends EntityPresenter {

    // Expenses
    public function vendor()
    {
        return $this->entity->vendor ? $this->entity->vendor->getDisplayName() : '';
    }

    public function expense_date()
    {
        return Utils::fromSqlDate($this->entity->expense_date);
    }

    public function invoiced_amount()
    {
        return $this->entity->invoice_id ? $this->entity->convertedAmount() : 0;
    }
    
}
