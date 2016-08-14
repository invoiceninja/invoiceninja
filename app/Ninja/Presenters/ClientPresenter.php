<?php namespace App\Ninja\Presenters;


class ClientPresenter extends EntityPresenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function balance()
    {
        $client = $this->entity;
        $account = $client->account;

        return $account->formatMoney($client->balance, $client);
    }

    public function paid_to_date()
    {
        $client = $this->entity;
        $account = $client->account;

        return $account->formatMoney($client->paid_to_date, $client);
    }

    public function status()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            $class = 'success';
            $text = trans('texts.active');
        }

        return "<span class=\"label label-{$class}\">{$text}</span>";
    }
}
