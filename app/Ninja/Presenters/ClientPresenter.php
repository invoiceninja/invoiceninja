<?php

namespace App\Ninja\Presenters;

use Utils;

class ClientPresenter extends EntityPresenter
{
    public function country()
    {
        return $this->entity->country ? $this->entity->country->getName() : '';
    }

    public function shipping_country()
    {
        return $this->entity->shipping_country ? $this->entity->shipping_country->getName() : '';
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

        $website = e($client->website);
        $link = Utils::addHttp($website);

        return link_to($link, $website, ['target' => '_blank']);
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

    public function address($addressType = ADDRESS_BILLING, $showHeader = false)
    {
        $str = '';
        $prefix = $addressType == ADDRESS_BILLING ? '' : 'shipping_';
        $client = $this->entity;

        if ($address1 = $client->{$prefix . 'address1'}) {
            $str .= e($address1) . '<br/>';
        }
        if ($address2 = $client->{$prefix . 'address2'}) {
            $str .= e($address2) . '<br/>';
        }
        if ($cityState = $this->getCityState($addressType)) {
            $str .= e($cityState) . '<br/>';
        }
        if ($country = $client->{$prefix . 'country'}) {
            $str .= e($country->getName()) . '<br/>';
        }

        if ($str && $showHeader) {
            $str = '<b>' . trans('texts.' . $addressType) . '</b><br/>' . $str;
        }

        return $str;
    }

    /**
     * @return string
     */
    public function getCityState($addressType = ADDRESS_BILLING)
    {
        $client = $this->entity;
        $prefix = $addressType == ADDRESS_BILLING ? '' : 'shipping_';
        $swap = $client->{$prefix . 'country'} && $client->{$prefix . 'country'}->swap_postal_code;

        $city = e($client->{$prefix . 'city'});
        $state = e($client->{$prefix . 'state'});
        $postalCode = e($client->{$prefix . 'postal_code'});

        if ($city || $state || $postalCode) {
            return Utils::cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }


    /**
     * @return string
     */
    public function taskRate()
    {
      if (floatval($this->entity->task_rate)) {
          return Utils::roundSignificant($this->entity->task_rate);
      } else {
          return '';
      }
    }

    /**
     * @return string
     */
    public function defaultTaskRate()
    {
      if ($rate = $this->taskRate()) {
          return $rate;
      } else {
          return $this->entity->account->present()->taskRate;
      }
    }

}
