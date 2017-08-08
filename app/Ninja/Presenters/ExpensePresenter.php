<?php

namespace App\Ninja\Presenters;

use Carbon;
use Utils;

/**
 * Class ExpensePresenter.
 */
class ExpensePresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function vendor()
    {
        return $this->entity->vendor ? $this->entity->vendor->getDisplayName() : '';
    }

    /**
     * @return \DateTime|string
     */
    public function expense_date()
    {
        return Utils::fromSqlDate($this->entity->expense_date);
    }

    /**
     * @return \DateTime|string
     */
    public function payment_date()
    {
        return Utils::fromSqlDate($this->entity->payment_date);
    }

    public function month()
    {
        return Carbon::parse($this->entity->payment_date)->format('Y m');
    }

    public function amount()
    {
        return Utils::formatMoney($this->entity->amount, $this->entity->expense_currency_id);
    }

    public function category()
    {
        return $this->entity->expense_category ? $this->entity->expense_category->name : '';
    }
}
