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

/**
 * Class UserPresenter.
 */
class UserPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name()
    {
        $first_name = isset($this->entity->first_name) ? $this->entity->first_name : '';
        $last_name = isset($this->entity->last_name) ? $this->entity->last_name : '';

        return $first_name.' '.$last_name;
    }
}
