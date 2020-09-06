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

use App\Utils\Ninja;
use App\Utils\SystemHealth;

trait AppSetup
{
    public function checkAppSetup()
    {
        if (Ninja::isNinja()) {  // Is this the invoice ninja production system?
            return Ninja::isNinja();
        }

        $check = SystemHealth::check();

        return $check['system_health'] == 'true';
    }
}
