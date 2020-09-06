<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models\Presenters;

use App\Models\Country;

/**
 * Class ClientPresenter.
 */
class ClientPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name()
    {
        if ($this->entity->name) {
            return $this->entity->name;
        }

        $contact = $this->entity->primary_contact->first();

        $contact_name = 'No Contact Set';

        if ($contact && (strlen($contact->first_name) >= 1 || strlen($contact->last_name) >= 1)) {
            $contact_name = $contact->first_name.' '.$contact->last_name;
        } elseif ($contact && (strlen($contact->email))) {
            $contact_name = $contact->email;
        }

        return $contact_name;
    }

    public function primary_contact_name()
    {
        return $this->entity->primary_contact->first() !== null ? $this->entity->primary_contact->first()->first_name.' '.$this->entity->primary_contact->first()->last_name : 'No primary contact set';
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
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $client->address2) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $client->country) {
            $str .= e($country->name).'<br/>';
        }

        return $str;
    }

    public function shipping_address()
    {
        $str = '';
        $client = $this->entity;

        if ($address1 = $client->shipping_address1) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $client->shipping_address2) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $client->country) {
            $str .= e($country->name).'<br/>';
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

    /**
     * Calculated company data fields
     * using settings.
     */
    public function company_name()
    {
        $settings = $this->entity->getMergedSettings();

        return $settings->name ?: ctrans('texts.untitled_account');
    }

    public function company_address()
    {
        $settings = $this->entity->getMergedSettings();

        $str = '';

        if ($settings->address1) {
            $str .= e($settings->address1).'<br/>';
        }
        if ($settings->address2) {
            $str .= e($settings->address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = Country::find($settings->country_id)) {
            $str .= e($country->name).'<br/>';
        }

        return $str;
    }

    public function getCityState()
    {
        $settings = $this->entity->getMergedSettings();

        $country = false;

        if ($settings->country_id) {
            $country = Country::find($settings->country_id);
        }

        $swap = $country && $country->swap_postal_code;

        $city = e($settings->city ?: '');
        $state = e($settings->state ?: '');
        $postalCode = e($settings->postal_code ?: '');

        if ($city || $state || $postalCode) {
            return $this->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }
}
