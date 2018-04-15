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
        return Carbon::parse($this->entity->expense_date)->format('Y m');
    }

    public function amount()
    {
        return Utils::formatMoney($this->entity->amountWithTax(), $this->entity->expense_currency_id);
    }

    public function currencyCode()
    {
        return Utils::getFromCache($this->entity->expense_currency_id, 'currencies')->code;
    }

    public function taxAmount()
    {
        return Utils::formatMoney($this->entity->taxAmount(), $this->entity->expense_currency_id);
    }

    public function category()
    {
        return $this->entity->expense_category ? $this->entity->expense_category->name : '';
    }

    public function payment_type()
    {
        if (! $this->payment_type_id) {
            return '';
        }

        return Utils::getFromCache($this->payment_type_id, 'paymentTypes')->name;
    }

    public function calendarEvent($subColors = false)
    {
        $data = parent::calendarEvent();
        $expense = $this->entity;

        $data->title = trans('texts.expense')  . ' ' . $this->amount() . ' | ' . $this->category();

        $data->title = trans('texts.expense') . ' ' . $this->amount();
        if ($category = $this->category()) {
            $data->title .= ' | ' . $category;
        }
        if ($this->public_notes) {
            $data->title .= ' | ' . $this->public_notes;
        }


        $data->start = $expense->expense_date;

        if ($subColors && $expense->expense_category_id) {
            $data->borderColor = $data->backgroundColor = Utils::brewerColor($expense->expense_category->public_id);
        } else {
            $data->borderColor = $data->backgroundColor = '#d95d02';
        }

        return $data;
    }
}
