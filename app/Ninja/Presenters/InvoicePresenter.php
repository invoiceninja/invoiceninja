<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class InvoicePresenter extends Presenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function balanceDueLabel()
    {
        if ($this->entity->partial) {
            return 'amount_due';
        } elseif ($this->entity->is_quote) {
            return 'total';
        } else {
            return 'balance_due';
        }
    }

    public function status()
    {
        $status = $this->entity->invoice_status ? $this->entity->invoice_status->name : 'draft';
        $status = strtolower($status);
        return trans("texts.status_{$status}");
    }

    public function invoice_date()
    {
        return Utils::fromSqlDate($this->entity->invoice_date);
    }

    public function due_date()
    {
        return Utils::fromSqlDate($this->entity->due_date);
    }

}