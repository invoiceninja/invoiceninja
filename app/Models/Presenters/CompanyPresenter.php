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
 * Class CompanyPresenter
 * @package App\Models\Presenters
 */
class CompanyPresenter extends EntityPresenter
{

    /**
     * @return string
     */
    public function name()
    {
        return $this->entity->name ?: ctrans('texts.untitled_account');
    }

    public function logo()
    {
        return strlen($this->entity->logo > 0) ? $this->entity->logo : 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png';
    }

    public function address()
    {
        $str = '';
        $company = $this->entity;

        if ($address1 = $company->settings->address1) {
            $str .= e($address1) . '<br/>';
        }
        if ($address2 = $company->settings->address2) {
            $str .= e($address2) . '<br/>';
        }
        if ($cityState = $this->getCompanyCityState()) {
            $str .= e($cityState) . '<br/>';
        }
        if ($country = $company->country()) {
            $str .= e($country->name) . '<br/>';
        }
        if ($company->settings->phone) {
            $str .= ctrans('texts.work_phone') . ": ". e($company->settings->phone) .'<br/>';
        }
        if ($company->settings->email) {
            $str .= ctrans('texts.work_email') . ": ". e($company->settings->email) .'<br/>';
        }

        return $str;
    }

    public function getCompanyCityState()
    {
        $company = $this->entity;

        $swap = $company->country() && $company->country()->swap_postal_code;

        $city = e($company->settings->city);
        $state = e($company->settings->state);
        $postalCode = e($company->settings->postal_code);

        if ($city || $state || $postalCode) {
            return $this->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }

}
