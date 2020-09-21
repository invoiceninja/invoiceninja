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

namespace App\Utils\ClientPortal\CustomMessage;

use Illuminate\Support\Facades\Facade;

class CustomMessageFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'customMessage';
    }
}
