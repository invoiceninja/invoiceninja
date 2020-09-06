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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

class PaymentHookController extends Controller
{
    public function process($company_gateway_id, $gateway_type_id)
    {
        $gateway = Gateway::find($company_gateway_id);

        dd(request()->input());
    }
}
