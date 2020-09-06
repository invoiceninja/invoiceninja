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

namespace App\Utils\Traits;

/**
 * Class UserSessionAttributes.
 */
trait UserSessionAttributes
{
    /**
     * @param $value
     */
    public function setCurrentCompanyId($value) : void
    {
        session(['current_company_id' => $value]);
    }

    /**
     * @return int
     */
    public function getCurrentCompanyId() : int
    {
        return session('current_company_id');
    }
}
