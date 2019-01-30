<?php

namespace App\Http\Requests;

class RecurringExpenseRequest extends ExpenseRequest
{
    protected $entityType = ENTITY_RECURRING_EXPENSE;
}
