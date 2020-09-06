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
 * Class ClientContactPresenter.
 */
class ClientContactPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name()
    {
        return $this->entity->first_name.' '.$this->entity->last_name;
    }

    public function first_name()
    {
        return $this->entity->first_name ?: '';
    }

    public function last_name()
    {
        return $this->entity->last_name ?: '';
    }
}
