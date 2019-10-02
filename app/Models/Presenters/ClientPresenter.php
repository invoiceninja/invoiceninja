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
        $contact = $this->entity->primary_contact->first();

        $contact_name = 'No Contact Set';

        if($contact)
            $contact_name = $contact->first_name. ' '. $contact->last_name;

        return $this->entity->name ?: $contact_name;
    }

    public function primary_contact_name()
    {
        return $this->entity->primary_contact->first() !== null ? $this->entity->primary_contact->first()->first_name . ' '. $this->entity->primary_contact->first()->last_name : 'No primary contact set';
    }

    public function email()
    {
        return $this->entity->primary_contact->first() !== null ? $this->entity->primary_contact->first()->email : 'No Email Set';
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

    public function phone()
    {
        return $this->entity->phone ?: '';
    }

    public function website()
    {
        return $this->entity->website ?: '';
    }
}
