<?php

namespace App\Ninja\Presenters;

use Utils;

class ClientPresenter extends EntityPresenter
{
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

    public function websiteLink()
    {
        $client = $this->entity;

        if (! $client->website) {
            return '';
        }

        $link = Utils::addHttp($client->website);

        return link_to($link, $client->website, ['target' => '_blank']);
    }

    public function paid_to_date()
    {
        $client = $this->entity;
        $account = $client->account;

        return $account->formatMoney($client->paid_to_date, $client);
    }

    public function paymentTerms()
    {
        $client = $this->entity;

        if (! $client->payment_terms) {
            return '';
        }

        return sprintf('%s: %s %s', trans('texts.payment_terms'), trans('texts.payment_terms_net'), $client->defaultDaysDue());
    }
}
