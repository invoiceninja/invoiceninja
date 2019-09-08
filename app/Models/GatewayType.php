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

use App\Models\Gateway;
use Illuminate\Database\Eloquent\Model;

class GatewayType extends Model
{

    public $timestamps = false;

    const CREDIT_CARD = 1;
    const BANK_TRANSFER = 2;
    const PAYPAL = 3;
    const BITCOIN = 4;
    const DWOLLA = 5;
    const CUSTOM1 = 6;
    const ALIPAY = 7;
    const SOFORT = 8;
    const SEPA = 9;
    const GOCARDLESS = 10;
    const APPLE_PAY = 11;
    const CUSTOM2 = 12;
    const CUSTOM3 = 13;
    const TOKEN = 'token');

	public function gateway()
	{
		return $this->belongsTo(Gateway::class);
	}

}


