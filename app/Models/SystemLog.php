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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    /* Category IDs */
    const PAYMENT_RESPONSE = 1;
    const GATEWAY_RESPONSE = 2;

    /* Event IDs*/
    const PAYMENT_RECONCILIATION_FAILURE = 10;
    const PAYMENT_RECONCILIATION_SUCCESS = 11;
    
    const GATEWAY_SUCCESS = 21;
    const GATEWAY_FAILURE = 22;
    const GATEWAY_ERROR = 23;
    
}
