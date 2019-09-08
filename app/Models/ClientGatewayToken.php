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
use App\Models\User;

class ClientGatewayToken extends BaseModel
{

	public function client()
	{
		return $this->hasOne(Client::class);
	}

	public function gateway()
	{
		return $this->hasOne(CompanyGateway::class);
	}

	public function company()
	{
		return $this->hasOne(Company::class);
	}

	public function user()
	{
		return $this->hasOne(User::class);
	}
	
}