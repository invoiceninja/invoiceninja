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

}
