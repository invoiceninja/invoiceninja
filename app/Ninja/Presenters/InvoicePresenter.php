<?php namespace App\Ninja\Presenters;

use URL;
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
        if ($this->entity->partial > 0) {
            return 'partial_due';
        } elseif ($this->entity->is_quote) {
            return 'total';
        } else {
            return 'balance_due';
        }
    }

    // https://schema.org/PaymentStatusType
    public function paymentStatus()
    {
        if ( ! $this->entity->balance) {
            return 'PaymentComplete';
        } elseif ($this->entity->isOverdue()) {
            return 'PaymentPastDue';
        } else {
            return 'PaymentDue';
        }
    }

    public function status()
    {
        if ($this->entity->is_deleted) {
            return trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            return trans('texts.archived');
        } else {
            $status = $this->entity->invoice_status ? $this->entity->invoice_status->name : 'draft';
            $status = strtolower($status);
            return trans("texts.status_{$status}");
        }
    }

    public function invoice_date()
    {
        return Utils::fromSqlDate($this->entity->invoice_date);
    }

    public function due_date()
    {
        return Utils::fromSqlDate($this->entity->due_date);
    }

    public function frequency()
    {
        return $this->entity->frequency ? $this->entity->frequency->name : '';
    }

    public function url()
    {
        return URL::to('/invoices/' . $this->entity->public_id);
    }

    public function link()
    {
        return link_to('/invoices/' . $this->entity->public_id, $this->entity->invoice_number);
    }

    public function email()
    {
        $client = $this->entity->client;
        return count($client->contacts) ? $client->contacts[0]->email : '';
    }
}