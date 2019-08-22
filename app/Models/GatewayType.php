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

	public function gateway()
	{
		return $this->belongsTo(Gateway::class);
	}
}


