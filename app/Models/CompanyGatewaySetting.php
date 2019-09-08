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

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CompanyGatewaySetting extends BaseModel
{

    public function company()
    {
    	return $this->belongsTo(Company::class);
    }

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function company_gateway()
    {
    	return $this->belongsTo(CompanyGateway::class);
    }
}
