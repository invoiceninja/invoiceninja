<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models\Presenters;

/**
 * Class ClientPresenter
 * @package App\Models\Presenters
 */
class ClientPresenter extends EntityPresenter
{

    /**
     * @return string
     */
    public function name()
    {
        return $this->entity->name ?: $this->entity->primary_contact->first()->first_name . ' '. $this->entity->primary_contact->first()->last_name;
    }

    public function address()
    {
        $str = '';
        $client = $this->entity;

        if ($address1 = $client->address1) {
            $str .= e($address1) . '<br/>';
        }
        if ($address2 = $client->address2) {
            $str .= e($address2) . '<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState) . '<br/>';
        }
        if ($country = $client->country) {
            $str .= e($country->name) . '<br/>';
        }

        return $str;
    }

    public function shipping_address()
    {
        $str = '';
        $client = $this->entity;

        if ($address1 = $client->shipping_address1) {
            $str .= e($address1) . '<br/>';
        }
        if ($address2 = $client->shipping_address2) {
            $str .= e($address2) . '<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState) . '<br/>';
        }
        if ($country = $client->country) {
            $str .= e($country->name) . '<br/>';
        }

        return $str;
    }



}
