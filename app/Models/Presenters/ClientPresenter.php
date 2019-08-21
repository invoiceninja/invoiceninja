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

    public function getCityState()
    {
        $client = $this->entity;
        $swap = $client->country && $client->country->swap_postal_code;

        $city = e($client->city);
        $state = e($client->state);
        $postalCode = e($client->postal_code);

        if ($city || $state || $postalCode) {
            return $this->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }

	public function cityStateZip($city, $state, $postalCode, $swap)
    {
        $str = $city;

        if ($state) {
            if ($str) {
                $str .= ', ';
            }
            $str .= $state;
        }

        if ($swap) {
            return $postalCode . ' ' . $str;
        } else {
            return $str . ' ' . $postalCode;
        }
    }

}
