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

    public function address()
    {
        $str = '';
        $company = $this->entity;

        if ($address1 = $company->address1) {
            $str .= e($address1) . '<br/>';
        }
        if ($address2 = $company->address2) {
            $str .= e($address2) . '<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState) . '<br/>';
        }
        if ($country = $company->country) {
            $str .= e($country->name) . '<br/>';
        }
        if ($company->work_phone) {
            $str .= ctrans('texts.work_phone') . ": ". e($company->work_phone) .'<br/>';
        }
        if ($company->work_email) {
            $str .= ctrans('texts.work_email') . ": ". e($company->work_email) .'<br/>';
        }

        return $str;
    }

}
